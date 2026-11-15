#!/bin/sh
set -eu

# Les métadonnées CI ont priorité et ne nécessitent aucun accès au dépôt distant.
if [ -n "${PWA_RELEASE_METADATA:-}" ]; then
    exit 0
fi

if [ "$(git --no-pager rev-parse --is-shallow-repository)" = true ]; then
    # Compléter l'histoire sans checkout/pull : les sources restent au commit déployé.
    GIT_TERMINAL_PROMPT=0 git fetch --unshallow --no-tags origin
fi
