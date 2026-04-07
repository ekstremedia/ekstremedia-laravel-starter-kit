#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
EXAMPLE_FILE="$ROOT_DIR/.env.example"

if [ ! -f "$ENV_FILE" ]; then
    cp "$EXAMPLE_FILE" "$ENV_FILE"
fi

prompt() {
    local label="$1"
    local default="$2"
    local value

    read -r -p "$label [$default]: " value
    echo "${value:-$default}"
}

set_env() {
    local key="$1"
    local value="$2"

    php -r '
        $file = $argv[1];
        $key = $argv[2];
        $value = $argv[3];
        $contents = file_get_contents($file);
        $line = $key."=".$value;

        if (preg_match("/^".preg_quote($key, "/")."=.*/m", $contents)) {
            $contents = preg_replace("/^".preg_quote($key, "/")."=.*/m", $line, $contents);
        } else {
            $contents .= PHP_EOL.$line.PHP_EOL;
        }

        file_put_contents($file, $contents);
    ' "$ENV_FILE" "$key" "$value"
}

app_name="$(prompt "App name" "Ekstremedia Laravel Starter Kit")"
app_url="$(prompt "App URL" "http://starter-kit.test")"
db_database="$(prompt "Database name" "starter")"
db_username="$(prompt "Database username" "starter")"
db_password="$(prompt "Database password" "secret")"
admin_first_name="$(prompt "Seeded admin first name" "Admin")"
admin_last_name="$(prompt "Seeded admin last name" "User")"
admin_email="$(prompt "Seeded admin email" "admin@example.test")"
admin_password="$(prompt "Seeded admin password" "password")"
easy_login_enabled="$(prompt "Enable local easy login (true/false)" "false")"

app_host="$(php -r '
    $url = $argv[1];
    $host = parse_url($url, PHP_URL_HOST);
    echo $host ?: $url;
' "$app_url")"

mail_domain="$app_host"
mail_domain="${mail_domain%%/*}"
storage_key="$(echo "$app_host" | tr '.-' '_' )_settings"

set_env "APP_NAME" "\"$app_name\""
set_env "APP_URL" "$app_url"
set_env "VITE_APP_NAME" "\"\${APP_NAME}\""
set_env "VITE_DEV_SERVER_HOST" "$app_host"
set_env "VITE_APP_STORAGE_KEY" "$storage_key"
set_env "BROADCAST_CONNECTION" "reverb"
set_env "DB_DATABASE" "$db_database"
set_env "DB_USERNAME" "$db_username"
set_env "DB_PASSWORD" "$db_password"
set_env "MAIL_FROM_ADDRESS" "\"hello@$mail_domain\""
set_env "MAIL_FROM_NAME" "\"\${APP_NAME}\""
set_env "STARTER_ADMIN_FIRST_NAME" "$admin_first_name"
set_env "STARTER_ADMIN_LAST_NAME" "$admin_last_name"
set_env "STARTER_ADMIN_EMAIL" "$admin_email"
set_env "STARTER_ADMIN_PASSWORD" "$admin_password"
set_env "DEV_EASY_LOGIN_ENABLED" "$easy_login_enabled"
set_env "REVERB_HOST" "127.0.0.1"
set_env "REVERB_SERVER_HOST" "0.0.0.0"
set_env "REVERB_SERVER_PORT" "8080"
set_env "VITE_REVERB_HOST" "$app_host"

echo ""
echo "Starter environment updated in $ENV_FILE"
echo "Next steps:"
echo "  1. Add '$app_host' to /etc/hosts if needed"
echo "  2. Run 'make build'"
echo "  3. Visit $app_url"
