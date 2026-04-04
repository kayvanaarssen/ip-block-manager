#!/usr/bin/env bash
set -euo pipefail

SCRIPT_VERSION="2.4.0"

BASE_DIR="/root/ip_blocks"
BLOCKLIST_FILE="${BASE_DIR}/blocked-ips.list"
LOG_FILE="${BASE_DIR}/ip-block.log"
NGINX_DENY_FILE="${BASE_DIR}/nginx-deny-ip.conf"
NGINX_CONF="/etc/nginx/nginx.conf"
NGINX_INCLUDE_LINE="include ${NGINX_DENY_FILE};"
# We need to check if error_page works; if not, deny alone is still fine (403)
NGINX_ERROR_PAGE_LINE="error_page 403 =444 /444-blocked;"

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
  $0 --install-nginx       Install nginx blocking in main nginx.conf (returns 444)
  $0 --migrate             Migrate/fix nginx config (clean up old configs)
  $0 --version             Show script version
  $0 --help                Show this help

Nginx blocking uses deny directives at the http level + error_page 403 =444
to drop connections from blocked IPs. Individual site configs are NEVER modified.
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
    touch "$BLOCKLIST_FILE" "$LOG_FILE" "$NGINX_DENY_FILE"
    chmod 600 "$BLOCKLIST_FILE" "$LOG_FILE"
    # nginx worker needs read access to the deny file
    chmod 644 "$NGINX_DENY_FILE"
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
# NGINX — deny directives at http level + error_page 403 =444
# All blocking happens via main nginx.conf. Site configs are
# NEVER touched.
# ============================================================

# Install deny include + error_page in nginx.conf http block
install_nginx_blocking() {
    has_command nginx || { echo "  [NGINX] not installed, skipping"; return 0; }

    local needs_include=0
    local needs_errorpage=0

    grep -Fq "$NGINX_INCLUDE_LINE" "$NGINX_CONF" || needs_include=1
    grep -Fq "$NGINX_ERROR_PAGE_LINE" "$NGINX_CONF" || needs_errorpage=1

    if (( !needs_include && !needs_errorpage )); then
        echo "  [NGINX] already configured (deny + 444)"
        return 0
    fi

    echo "  [NGINX] Installing blocking in nginx.conf"
    local b
    b=$(backup_file "$NGINX_CONF")

    # Build the lines to insert after "http {"
    local insert_lines=""
    if (( needs_include )); then
        insert_lines="    ${NGINX_INCLUDE_LINE}"
    fi
    if (( needs_errorpage )); then
        if [[ -n "$insert_lines" ]]; then
            insert_lines="${insert_lines}\n    ${NGINX_ERROR_PAGE_LINE}"
        else
            insert_lines="    ${NGINX_ERROR_PAGE_LINE}"
        fi
    fi

    awk -v lines="$insert_lines" '
        BEGIN{ok=0}
        {print}
        /http[[:space:]]*\{/ && !ok {print lines; ok=1}
        END{ if(!ok) exit 1 }
    ' "$b" > "${NGINX_CONF}.tmp"
    mv "${NGINX_CONF}.tmp" "$NGINX_CONF"

    local nginx_test
    nginx_test=$(nginx -t 2>&1) || true
    if echo "$nginx_test" | grep -q "test failed"; then
        echo -e "${YELLOW}  [NGINX] config with error_page failed, trying deny-only${NC}"
        echo "  [NGINX] Error: $nginx_test"

        # Restore and try again with just the deny include (no error_page)
        cp -a "$b" "$NGINX_CONF"

        if (( needs_include )); then
            local b2
            b2=$(backup_file "$NGINX_CONF")
            awk -v lines="    ${NGINX_INCLUDE_LINE}" '
                BEGIN{ok=0}
                {print}
                /http[[:space:]]*\{/ && !ok {print lines; ok=1}
                END{ if(!ok) exit 1 }
            ' "$b2" > "${NGINX_CONF}.tmp"
            mv "${NGINX_CONF}.tmp" "$NGINX_CONF"

            nginx_test=$(nginx -t 2>&1) || true
            if echo "$nginx_test" | grep -q "test failed"; then
                echo -e "${RED}  [NGINX] deny-only also failed — restoring backup${NC}"
                echo "  [NGINX] Error: $nginx_test"
                cp -a "$b2" "$NGINX_CONF"
                return 1
            fi
        else
            echo "  [NGINX] deny include already present, error_page 444 not supported by this nginx"
        fi

        systemctl reload nginx
        echo -e "${YELLOW}  [NGINX] blocking installed (deny only — returns 403, not 444)${NC}"
        echo "  [NGINX] Note: error_page 403 =444 not supported, blocked IPs get 403 instead"
        return 0
    fi

    systemctl reload nginx
    echo -e "${GREEN}  [NGINX] blocking installed (deny + error_page 403 =444)${NC}"
}

# Clean up any v2.0.0 geo block / server snippet mess and old error_page lines
cleanup_geo_block() {
    local cleaned=0

    # Remove old error_page lines from previous attempts
    for old_ep in "error_page 403 =444 /;" "error_page 403 =444 /444.html;" "error_page 403 =444 /444-blocked;"; do
        if grep -Fq "$old_ep" "$NGINX_CONF"; then
            echo "  [NGINX] Removing old error_page line: $old_ep"
            grep -Fv "$old_ep" "$NGINX_CONF" > "${NGINX_CONF}.tmp"
            mv "${NGINX_CONF}.tmp" "$NGINX_CONF"
            cleaned=1
        fi
    done

    # Remove geo block from nginx.conf if present
    if grep -Fq 'geo $blocked_ip' "$NGINX_CONF"; then
        echo "  [NGINX] Removing old geo block from nginx.conf"
        local b
        b=$(backup_file "$NGINX_CONF")

        # Remove the geo block (multi-line)
        awk '
            /geo \$blocked_ip \{/ { skip=1; next }
            skip && /\}/ { skip=0; next }
            skip { next }
            { print }
        ' "$b" > "${NGINX_CONF}.tmp"
        mv "${NGINX_CONF}.tmp" "$NGINX_CONF"
        cleaned=1
    fi

    # Remove server snippet includes from site configs
    local check_file="${BASE_DIR}/nginx-blocked-check.conf"
    local check_include="include ${check_file};"
    local sites_dir="/etc/nginx/sites-enabled"

    if [[ -d "$sites_dir" ]]; then
        for conf_file in "$sites_dir"/*; do
            [[ -f "$conf_file" ]] || continue
            if grep -Fq "$check_include" "$conf_file"; then
                echo "  [NGINX] Removing snippet from $(basename "$conf_file")"
                grep -Fv "$check_include" "$conf_file" > "${conf_file}.tmp"
                mv "${conf_file}.tmp" "$conf_file"
                cleaned=1
            fi
        done
    fi

    # Remove snippet file and geo-format file
    rm -f "$check_file" "${BASE_DIR}/nginx-blocked-ips.conf"

    if (( cleaned )); then
        echo "  [NGINX] Cleaned up old geo/snippet config"
    fi
}

# Convert geo-format entries (IP 1;) back to deny format (deny IP;)
convert_geo_to_deny() {
    local geo_file="${BASE_DIR}/nginx-blocked-ips.conf"
    if [[ -f "$geo_file" ]] && [[ -s "$geo_file" ]]; then
        echo "  [NGINX] Converting geo entries back to deny format"
        while IFS= read -r line; do
            local ip
            ip=$(echo "$line" | sed -n 's/^\(.*\) 1;$/\1/p')
            if [[ -n "$ip" ]]; then
                # Add to deny file if not already there
                grep -Fxq "deny $ip;" "$NGINX_DENY_FILE" 2>/dev/null || echo "deny $ip;" >> "$NGINX_DENY_FILE"
            fi
        done < "$geo_file"
    fi
}

# Full migration: clean up v2.0.0 mess, install correct config
migrate_nginx() {
    has_command nginx || { echo "  [NGINX] not installed, skipping"; return 0; }

    echo "Migrating nginx config..."

    # Convert any geo-format entries back to deny
    convert_geo_to_deny

    # Clean up geo blocks and server snippets
    cleanup_geo_block

    # Install the correct config (deny + error_page 403 =444)
    install_nginx_blocking

    # Ensure all registered IPs are in the deny file
    if [[ -s "$BLOCKLIST_FILE" ]]; then
        while IFS= read -r ip; do
            [[ -z "$ip" ]] && continue
            grep -Fxq "deny $ip;" "$NGINX_DENY_FILE" 2>/dev/null || echo "deny $ip;" >> "$NGINX_DENY_FILE"
        done < "$BLOCKLIST_FILE"
        echo "  [NGINX] Synced $(wc -l < "$NGINX_DENY_FILE") deny entries"
    fi

    if nginx -t 2>/dev/null; then
        systemctl reload nginx
    fi

    log_action migrate-nginx "n/a"
    echo -e "${GREEN}Migration complete — nginx returns 444 for blocked IPs${NC}"
}

nginx_deny_exists() {
    grep -Fxq "deny $1;" "$NGINX_DENY_FILE" 2>/dev/null
}

ensure_nginx_installed() {
    if ! grep -Fq "$NGINX_INCLUDE_LINE" "$NGINX_CONF" 2>/dev/null; then
        install_nginx_blocking || true
    fi
}

block_nginx() {
    has_command nginx || return 0
    ensure_nginx_installed || true
    if nginx_deny_exists "$1"; then
        echo "  [NGINX] already blocked"
        return 0
    fi
    echo "deny $1;" >> "$NGINX_DENY_FILE"
    if nginx -t 2>/dev/null; then
        systemctl reload nginx
        echo "  [NGINX] deny rule added (returns 444)"
    else
        echo -e "${RED}  [NGINX] config test failed — removing last entry${NC}"
        grep -Fxv "deny $1;" "$NGINX_DENY_FILE" > "${NGINX_DENY_FILE}.tmp" || true
        mv "${NGINX_DENY_FILE}.tmp" "$NGINX_DENY_FILE"
        return 1
    fi
}

unblock_nginx() {
    has_command nginx || return 0
    if ! nginx_deny_exists "$1"; then
        echo "  [NGINX] no rule found"
        return 0
    fi
    grep -Fxv "deny $1;" "$NGINX_DENY_FILE" > "${NGINX_DENY_FILE}.tmp" || true
    mv "${NGINX_DENY_FILE}.tmp" "$NGINX_DENY_FILE"
    if nginx -t 2>/dev/null; then
        systemctl reload nginx
        echo "  [NGINX] deny rule removed and reloaded"
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
        # Use --force to skip confirmation prompt — avoids yes|pipe + pipefail SIGPIPE bug
        ufw --force delete "$n" >/dev/null 2>&1 || true
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
    if grep -Fxq "deny $ip;" "$NGINX_DENY_FILE" 2>/dev/null; then
        echo -e "${RED}BLOCKED (444 drop)${NC}"
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
    # Each system is independent — never let one failure stop the others
    block_ufw "$ip" || echo -e "${YELLOW}  [UFW] warning: failed${NC}"
    block_fail2ban "$ip" || echo -e "${YELLOW}  [Fail2Ban] warning: failed${NC}"
    block_nginx "$ip" || echo -e "${YELLOW}  [NGINX] warning: failed${NC}"
    add_to_registry "$ip"
    log_action block "$ip"
    echo -e "${GREEN}Blocked ${ip}${NC}"
}

unblock_ip() {
    local ip="$1"
    echo -e "Unblocking ${YELLOW}${ip}${NC} ..."
    # Each system is independent — never let one failure stop the others
    unblock_ufw "$ip" || echo -e "${YELLOW}  [UFW] warning: failed${NC}"
    unblock_fail2ban "$ip" || echo -e "${YELLOW}  [Fail2Ban] warning: failed${NC}"
    unblock_nginx "$ip" || echo -e "${YELLOW}  [NGINX] warning: failed${NC}"
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
