#!/usr/bin/env bash
set -euo pipefail

SCRIPT_VERSION="2.0.0"

BASE_DIR="/root/ip_blocks"
BLOCKLIST_FILE="${BASE_DIR}/blocked-ips.list"
LOG_FILE="${BASE_DIR}/ip-block.log"
NGINX_BLOCK_FILE="${BASE_DIR}/nginx-blocked-ips.conf"
NGINX_CHECK_FILE="${BASE_DIR}/nginx-blocked-check.conf"
NGINX_CONF="/etc/nginx/nginx.conf"
NGINX_SITES_DIR="/etc/nginx/sites-enabled"

# Legacy file (will be migrated)
LEGACY_DENY_FILE="${BASE_DIR}/nginx-deny-ip.conf"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

usage() {
    cat <<EOM
Usage:
  $0 --block <ip>          Block an IPv4/IPv6 address or CIDR range
  $0 --unblock <ip>        Unblock an IPv4/IPv6 address or CIDR range
  $0 --status <ip>         Check block status of an IP
  $0 --list                List all blocked IPs
  $0 --install-nginx       Install/upgrade nginx blocking (geo block + 444)
  $0 --migrate             Migrate from old deny-based to new 444-based blocking
  $0 --help                Show this help

Nginx blocking returns 444 (drop connection) instead of 403 (forbidden).
EOM
    exit 0
}

log_action() {
    local action="$1"
    local ip="${2:-n/a}"
    local user_name="${SUDO_USER:-$(whoami)}"
    local host_name
    host_name="$(hostname)"
    echo "$(date '+%Y-%m-%d %H:%M:%S') | user=${user_name} | host=${host_name} | action=${action} | ip=${ip}" >> "$LOG_FILE"
}

ensure_files() {
    mkdir -p "$BASE_DIR"
    chmod 700 "$BASE_DIR"
    touch "$BLOCKLIST_FILE" "$LOG_FILE" "$NGINX_BLOCK_FILE"
    chmod 600 "$BLOCKLIST_FILE" "$LOG_FILE"
    # nginx worker needs read access to the block file
    chmod 644 "$NGINX_BLOCK_FILE"

    # Create the server-block snippet if it doesn't exist
    if [[ ! -f "$NGINX_CHECK_FILE" ]]; then
        cat > "$NGINX_CHECK_FILE" <<'SNIPPET'
# IP Block Manager — return 444 for blocked IPs
if ($blocked_ip) {
    return 444;
}
SNIPPET
        chmod 644 "$NGINX_CHECK_FILE"
    fi
}

require_root() {
    [[ "$EUID" -eq 0 ]] || { echo -e "${RED}Error: must run as root${NC}"; exit 1; }
}

validate_ipv4() {
    local ip="$1"
    [[ "$ip" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]] || return 1
    local IFS='.'
    read -r o1 o2 o3 o4 <<< "$ip"
    for o in "$o1" "$o2" "$o3" "$o4"; do
        (( o >= 0 && o <= 255 )) || return 1
    done
}

validate_ipv4_cidr() {
    local cidr="$1"
    [[ "$cidr" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}/([0-9]{1,2})$ ]] || return 1
    local ip="${cidr%/*}"
    local prefix="${cidr#*/}"
    validate_ipv4 "$ip" || return 1
    (( prefix >= 0 && prefix <= 32 )) || return 1
}

validate_ipv6() {
    local ip="$1"
    [[ "$ip" =~ ^([0-9a-fA-F]{0,4}:){1,7}[0-9a-fA-F]{0,4}$ ]] || \
    [[ "$ip" =~ ^::([0-9a-fA-F]{1,4}:){0,5}[0-9a-fA-F]{0,4}$ ]] || \
    [[ "$ip" =~ ^[0-9a-fA-F]{1,4}::([0-9a-fA-F]{1,4}:){0,4}[0-9a-fA-F]{0,4}$ ]] || \
    return 1
}

validate_ipv6_cidr() {
    local cidr="$1"
    [[ "$cidr" =~ / ]] || return 1
    local ip="${cidr%/*}"
    local prefix="${cidr#*/}"
    validate_ipv6 "$ip" || return 1
    [[ "$prefix" =~ ^[0-9]+$ ]] || return 1
    (( prefix >= 0 && prefix <= 128 )) || return 1
}

validate_ip() {
    local ip="$1"
    validate_ipv4 "$ip" && return 0
    validate_ipv4_cidr "$ip" && return 0
    validate_ipv6 "$ip" && return 0
    validate_ipv6_cidr "$ip" && return 0
    echo -e "${RED}Error: invalid IP address or CIDR range: ${ip}${NC}" >&2
    return 1
}

has_command() { command -v "$1" >/dev/null 2>&1; }

add_to_registry() {
    local ip="$1"
    grep -Fxq "$ip" "$BLOCKLIST_FILE" 2>/dev/null || echo "$ip" >> "$BLOCKLIST_FILE"
    sort -u "$BLOCKLIST_FILE" -o "$BLOCKLIST_FILE"
}

remove_from_registry() {
    local ip="$1"
    grep -Fxv "$ip" "$BLOCKLIST_FILE" > "${BLOCKLIST_FILE}.tmp" || true
    mv "${BLOCKLIST_FILE}.tmp" "$BLOCKLIST_FILE"
}

backup_file() {
    local f="$1"
    local b="${f}.bak.$(date +%s)"
    cp -a "$f" "$b"
    echo "$b"
}

# ============================================================
# NGINX — geo block + return 444 approach
# ============================================================

# Install the geo block in nginx.conf http block
install_nginx_geo_block() {
    local geo_start="geo \$blocked_ip {"
    local geo_include="    include ${NGINX_BLOCK_FILE};"

    # Already installed?
    if grep -Fq "$geo_start" "$NGINX_CONF"; then
        echo "  [NGINX] geo block already in nginx.conf"
        return 0
    fi

    echo "  [NGINX] Installing geo block in nginx.conf"
    local b
    b=$(backup_file "$NGINX_CONF")

    # Remove legacy deny-file include if present
    local legacy_include="include /root/ip_blocks/nginx-deny-ip.conf;"
    if grep -Fq "$legacy_include" "$NGINX_CONF"; then
        echo "  [NGINX] Removing legacy deny include"
        grep -Fv "$legacy_include" "$b" > "${NGINX_CONF}.tmp"
        mv "${NGINX_CONF}.tmp" "$b"
    fi

    # Insert geo block after "http {"
    awk -v geo_block="    geo \$blocked_ip {\n        default 0;\n        include ${NGINX_BLOCK_FILE};\n    }" '
        BEGIN{ok=0}
        {print}
        /http[[:space:]]*\{/ && !ok {print geo_block; ok=1}
        END{ if(!ok) exit 1 }
    ' "$b" > "${NGINX_CONF}.tmp"
    mv "${NGINX_CONF}.tmp" "$NGINX_CONF"

    if ! nginx -t 2>/dev/null; then
        echo -e "${RED}  [NGINX] config test failed — restoring backup${NC}"
        cp -a "$b" "$NGINX_CONF"
        return 1
    fi
    echo "  [NGINX] geo block installed"
}

# Install the 444 check snippet into all server blocks in sites-enabled
install_nginx_server_snippets() {
    local check_include="include ${NGINX_CHECK_FILE};"

    if [[ ! -d "$NGINX_SITES_DIR" ]]; then
        echo "  [NGINX] No sites-enabled directory found, skipping snippet injection"
        return 0
    fi

    local modified=0
    for conf_file in "$NGINX_SITES_DIR"/*; do
        [[ -f "$conf_file" ]] || continue

        if grep -Fq "$check_include" "$conf_file"; then
            continue
        fi

        echo "  [NGINX] Adding 444 snippet to $(basename "$conf_file")"
        local b
        b=$(backup_file "$conf_file")

        # Insert the include after the first "server {" line
        awk -v inc="        ${check_include}" '
            BEGIN{done=0}
            {print}
            /server[[:space:]]*\{/ && !done {print inc; done=1}
        ' "$b" > "${conf_file}.tmp"
        mv "${conf_file}.tmp" "$conf_file"
        modified=1
    done

    if (( modified )); then
        if ! nginx -t 2>/dev/null; then
            echo -e "${RED}  [NGINX] config test failed after snippet injection — check manually${NC}"
            return 1
        fi
        echo "  [NGINX] snippets installed in server blocks"
    else
        echo "  [NGINX] all server blocks already have snippets"
    fi
}

# Full nginx setup
install_nginx_blocking() {
    has_command nginx || { echo "  [NGINX] not installed, skipping"; return 0; }
    install_nginx_geo_block
    install_nginx_server_snippets
    systemctl reload nginx
    echo -e "${GREEN}  [NGINX] blocking setup complete (returns 444)${NC}"
}

# Migrate from old deny-based to new geo/444 approach
migrate_nginx() {
    has_command nginx || { echo "  [NGINX] not installed, skipping"; return 0; }

    echo "Migrating nginx blocking from 403 deny to 444 drop..."

    # Convert old deny file entries to new geo format
    if [[ -f "$LEGACY_DENY_FILE" ]] && [[ -s "$LEGACY_DENY_FILE" ]]; then
        echo "  [NGINX] Converting legacy deny entries to geo format"
        local temp_file="${NGINX_BLOCK_FILE}.migrate"
        > "$temp_file"

        while IFS= read -r line; do
            # Extract IP from "deny IP;" format
            local ip
            ip=$(echo "$line" | sed -n 's/^deny \(.*\);$/\1/p')
            if [[ -n "$ip" ]]; then
                echo "$ip 1;" >> "$temp_file"
            fi
        done < "$LEGACY_DENY_FILE"

        if [[ -s "$temp_file" ]]; then
            # Merge with any existing geo entries
            if [[ -s "$NGINX_BLOCK_FILE" ]]; then
                cat "$temp_file" >> "$NGINX_BLOCK_FILE"
                sort -u "$NGINX_BLOCK_FILE" -o "$NGINX_BLOCK_FILE"
            else
                mv "$temp_file" "$NGINX_BLOCK_FILE"
            fi
            chmod 644 "$NGINX_BLOCK_FILE"
            echo "  [NGINX] Converted $(wc -l < "$NGINX_BLOCK_FILE") entries"
        else
            rm -f "$temp_file"
        fi

        # Keep backup of old file, remove original
        mv "$LEGACY_DENY_FILE" "${LEGACY_DENY_FILE}.migrated.$(date +%s)"
        echo "  [NGINX] Legacy deny file archived"
    fi

    # Remove old deny includes from server configs
    local old_deny_include="include /root/ip_blocks/nginx-deny-ip.conf;"
    if [[ -d "$NGINX_SITES_DIR" ]]; then
        for conf_file in "$NGINX_SITES_DIR"/*; do
            [[ -f "$conf_file" ]] || continue
            if grep -Fq "$old_deny_include" "$conf_file"; then
                echo "  [NGINX] Removing old deny include from $(basename "$conf_file")"
                grep -Fv "$old_deny_include" "$conf_file" > "${conf_file}.tmp"
                mv "${conf_file}.tmp" "$conf_file"
            fi
        done
    fi

    # Install new geo block + snippets
    install_nginx_blocking

    log_action migrate-nginx "n/a"
    echo -e "${GREEN}Migration complete — nginx now returns 444 for blocked IPs${NC}"
}

# Check if nginx blocking is set up (new style)
nginx_is_setup() {
    grep -Fq "geo \$blocked_ip" "$NGINX_CONF" 2>/dev/null
}

# Ensure nginx is set up on first block
ensure_nginx_setup() {
    if ! nginx_is_setup; then
        # Check if we need to migrate from old format
        if [[ -f "$LEGACY_DENY_FILE" ]] && [[ -s "$LEGACY_DENY_FILE" ]]; then
            migrate_nginx
        else
            install_nginx_blocking
        fi
    fi
}

nginx_block_exists() {
    grep -Fq "$1 1;" "$NGINX_BLOCK_FILE" 2>/dev/null
}

block_nginx() {
    has_command nginx || return 0
    ensure_nginx_setup
    if nginx_block_exists "$1"; then
        echo "  [NGINX] already blocked"
        return 0
    fi
    echo "$1 1;" >> "$NGINX_BLOCK_FILE"
    if nginx -t 2>/dev/null; then
        systemctl reload nginx
        echo "  [NGINX] blocked (444 drop)"
    else
        echo -e "${RED}  [NGINX] config test failed — removing last entry${NC}"
        grep -Fxv "$1 1;" "$NGINX_BLOCK_FILE" > "${NGINX_BLOCK_FILE}.tmp" || true
        mv "${NGINX_BLOCK_FILE}.tmp" "$NGINX_BLOCK_FILE"
        return 1
    fi
}

unblock_nginx() {
    has_command nginx || return 0
    if ! nginx_block_exists "$1"; then
        echo "  [NGINX] no rule found"
        return 0
    fi
    grep -Fxv "$1 1;" "$NGINX_BLOCK_FILE" > "${NGINX_BLOCK_FILE}.tmp" || true
    mv "${NGINX_BLOCK_FILE}.tmp" "$NGINX_BLOCK_FILE"
    if nginx -t 2>/dev/null; then
        systemctl reload nginx
        echo "  [NGINX] rule removed and reloaded"
    else
        echo -e "${YELLOW}  [NGINX] warning: config test failed after removal${NC}"
    fi
}

# ============================================================
# UFW
# ============================================================

ufw_rule_exists() {
    ufw status | grep -wF "$1" | grep -q "DENY"
}

block_ufw() {
    has_command ufw || return 0
    if ufw_rule_exists "$1"; then
        echo "  [UFW] already blocked"
        return 0
    fi
    ufw insert 1 deny from "$1" to any >/dev/null
    echo "  [UFW] deny rule added"
}

unblock_ufw() {
    has_command ufw || return 0
    local removed=0
    while true; do
        local n
        n=$(ufw status numbered | awk -v ip="$1" 'index($0,ip) && /DENY/{gsub(/[][]/,"",$1);print $1;exit}')
        [[ -z "$n" ]] && break
        yes | ufw delete "$n" >/dev/null
        removed=1
    done
    if (( removed )); then
        echo "  [UFW] rule(s) removed"
    else
        echo "  [UFW] no rules found"
    fi
}

# ============================================================
# Fail2Ban
# ============================================================

get_jails() {
    fail2ban-client status 2>/dev/null \
        | sed -n 's/.*Jail list:[[:space:]]*//p' \
        | tr ',' '\n' \
        | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' \
        | grep -v '^$'
}

block_fail2ban() {
    has_command fail2ban-client || return 0
    local jails
    jails=$(get_jails)
    [[ -z "$jails" ]] && return 0
    echo "$jails" | while read -r j; do
        fail2ban-client set "$j" banip "$1" 2>/dev/null || true
    done
    echo "  [Fail2Ban] banned in all jails"
}

unblock_fail2ban() {
    has_command fail2ban-client || return 0
    local jails
    jails=$(get_jails)
    [[ -z "$jails" ]] && return 0
    echo "$jails" | while read -r j; do
        fail2ban-client set "$j" unbanip "$1" 2>/dev/null || true
    done
    echo "  [Fail2Ban] unbanned from all jails"
}

# ============================================================
# Status
# ============================================================

status_ip() {
    local ip="$1"
    local found=0

    echo "=== Status for $ip ==="

    echo -n "UFW:       "
    if has_command ufw && ufw status | grep -wF "$ip" | grep -q "DENY"; then
        echo -e "${RED}BLOCKED${NC}"
        found=1
    else
        echo "not blocked"
    fi

    echo -n "Fail2Ban:  "
    if has_command fail2ban-client; then
        local banned_in=""
        while read -r j; do
            [[ -z "$j" ]] && continue
            if fail2ban-client status "$j" 2>/dev/null | grep -wqF "$ip"; then
                banned_in="${banned_in} ${j}"
            fi
        done <<< "$(get_jails)"
        if [[ -n "$banned_in" ]]; then
            echo -e "${RED}BANNED in:${banned_in}${NC}"
            found=1
        else
            echo "not banned"
        fi
    else
        echo "not installed"
    fi

    echo -n "NGINX:     "
    if grep -Fq "$ip 1;" "$NGINX_BLOCK_FILE" 2>/dev/null; then
        echo -e "${RED}BLOCKED (444 drop)${NC}"
        found=1
    elif [[ -f "$LEGACY_DENY_FILE" ]] && grep -Fq "deny $ip;" "$LEGACY_DENY_FILE" 2>/dev/null; then
        echo -e "${YELLOW}DENIED (403 legacy — run --migrate)${NC}"
        found=1
    else
        echo "not blocked"
    fi

    echo -n "Registry:  "
    if grep -Fxq "$ip" "$BLOCKLIST_FILE" 2>/dev/null; then
        echo -e "${RED}LISTED${NC}"
        found=1
    else
        echo "not listed"
    fi

    (( found )) && return 0
    echo -e "${GREEN}IP is not blocked anywhere${NC}"
}

# ============================================================
# Main actions
# ============================================================

block_ip() {
    local ip="$1"
    echo -e "Blocking ${YELLOW}${ip}${NC} ..."
    block_ufw "$ip"
    block_fail2ban "$ip"
    block_nginx "$ip"
    add_to_registry "$ip"
    log_action block "$ip"
    echo -e "${GREEN}Blocked ${ip}${NC}"
}

unblock_ip() {
    local ip="$1"
    echo -e "Unblocking ${YELLOW}${ip}${NC} ..."
    unblock_ufw "$ip"
    unblock_fail2ban "$ip"
    unblock_nginx "$ip"
    remove_from_registry "$ip"
    log_action unblock "$ip"
    echo -e "${GREEN}Unblocked ${ip}${NC}"
}

require_arg() {
    if [[ -z "${2:-}" ]]; then
        echo -e "${RED}Error: $1 requires an IP address argument${NC}" >&2
        exit 1
    fi
}

main() {
    require_root
    ensure_files

    case "${1:-}" in
        --block)
            require_arg "--block" "${2:-}"
            validate_ip "$2" && block_ip "$2"
            ;;
        --unblock)
            require_arg "--unblock" "${2:-}"
            validate_ip "$2" && unblock_ip "$2"
            ;;
        --status)
            require_arg "--status" "${2:-}"
            validate_ip "$2" && status_ip "$2"
            ;;
        --list)
            if [[ -s "$BLOCKLIST_FILE" ]]; then
                echo "Blocked IPs:"
                cat "$BLOCKLIST_FILE"
            else
                echo "No IPs currently blocked"
            fi
            ;;
        --install-nginx)
            install_nginx_blocking
            ;;
        --migrate)
            migrate_nginx
            ;;
        --version)
            echo "$SCRIPT_VERSION"
            ;;
        --help|-h)
            usage
            ;;
        *)
            echo -e "${RED}Unknown option: ${1:-}${NC}" >&2
            usage
            ;;
    esac
}

main "$@"
