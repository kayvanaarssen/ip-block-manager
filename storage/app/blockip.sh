#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="/root/ip_blocks"
BLOCKLIST_FILE="${BASE_DIR}/blocked-ips.list"
LOG_FILE="${BASE_DIR}/ip-block.log"
NGINX_BLOCK_FILE="${BASE_DIR}/nginx-deny-ip.conf"
NGINX_CONF="/etc/nginx/nginx.conf"
NGINX_INCLUDE_LINE="include /root/ip_blocks/nginx-deny-ip.conf;"

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
  $0 --install-nginx-include   Install the nginx include directive
  $0 --help                Show this help
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
    # nginx worker needs read access to the deny file
    chmod 644 "$NGINX_BLOCK_FILE"
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
    # Basic IPv6 validation — accept standard and compressed forms
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

ensure_nginx_include_installed() {
    grep -Fq "$NGINX_INCLUDE_LINE" "$NGINX_CONF" && return 0
    echo "[NGINX] Installing global include directive"
    local b
    b=$(backup_file "$NGINX_CONF")
    awk -v l="    $NGINX_INCLUDE_LINE" '
        BEGIN{ok=0}
        {print}
        /http[[:space:]]*\{/ && !ok {print l; ok=1}
        END{ if(!ok) exit 1 }
    ' "$b" > "${NGINX_CONF}.tmp"
    mv "${NGINX_CONF}.tmp" "$NGINX_CONF"
    if ! nginx -t 2>/dev/null; then
        echo -e "${RED}nginx config test failed — restoring backup${NC}"
        cp -a "$b" "$NGINX_CONF"
        exit 1
    fi
    systemctl reload nginx
}

# --- UFW ---

ufw_rule_exists() {
    # Use word boundary matching to avoid partial IP matches
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

# --- Fail2Ban ---

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

# --- NGINX ---

nginx_block_exists() {
    # Exact line match to prevent partial IP collisions
    grep -Fxq "deny $1;" "$NGINX_BLOCK_FILE" 2>/dev/null
}

block_nginx() {
    has_command nginx || return 0
    ensure_nginx_include_installed
    if nginx_block_exists "$1"; then
        echo "  [NGINX] already blocked"
        return 0
    fi
    echo "deny $1;" >> "$NGINX_BLOCK_FILE"
    if nginx -t 2>/dev/null; then
        systemctl reload nginx
        echo "  [NGINX] deny rule added and reloaded"
    else
        echo -e "${RED}  [NGINX] config test failed — removing last entry${NC}"
        # Remove the line we just added
        grep -Fxv "deny $1;" "$NGINX_BLOCK_FILE" > "${NGINX_BLOCK_FILE}.tmp" || true
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
    grep -Fxv "deny $1;" "$NGINX_BLOCK_FILE" > "${NGINX_BLOCK_FILE}.tmp" || true
    mv "${NGINX_BLOCK_FILE}.tmp" "$NGINX_BLOCK_FILE"
    if nginx -t 2>/dev/null; then
        systemctl reload nginx
        echo "  [NGINX] deny rule removed and reloaded"
    else
        echo -e "${YELLOW}  [NGINX] warning: config test failed after removal${NC}"
    fi
}

# --- Status ---

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
    if grep -Fxq "deny $ip;" "$NGINX_BLOCK_FILE" 2>/dev/null; then
        echo -e "${RED}DENIED${NC}"
        found=1
    else
        echo "not denied"
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

# --- Main actions ---

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
        --install-nginx-include)
            ensure_nginx_include_installed
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
