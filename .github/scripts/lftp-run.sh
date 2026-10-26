#!/usr/bin/env bash
# Runs lftp commands (read from stdin) against the Pressable SFTP site.
# Needs SFTP_HOST, SFTP_USER and SFTP_PASSWORD in the environment; SFTP_PORT defaults to 22.
#
# The password is passed to lftp inside its script (stdin), never on a command line,
# and the secret is masked by GitHub in the logs. The old way (LFTP_PASSWORD with
# "lftp -u user") was not picked up on the runner, so lftp tried an anonymous login.
set -euo pipefail

: "${SFTP_HOST:?SFTP_HOST is not set}"
: "${SFTP_USER:?SFTP_USER is not set}"
: "${SFTP_PASSWORD:?SFTP_PASSWORD is not set}"
SFTP_PORT="${SFTP_PORT:-22}"

mkdir -p ~/.ssh
ssh-keyscan -p "$SFTP_PORT" "$SFTP_HOST" >> ~/.ssh/known_hosts 2>/dev/null

# lftp reads quoted arguments: escape backslashes and double quotes in the password.
pw=$(printf '%s' "$SFTP_PASSWORD" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')

{
  echo "set cmd:fail-exit yes"
  echo "set net:max-retries 2"
  echo "set net:timeout 30"
  echo "open -u \"$SFTP_USER,$pw\" \"sftp://$SFTP_HOST:$SFTP_PORT\""
  cat
} | lftp
