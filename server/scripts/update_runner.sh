#!/bin/bash

# padding handles script being overwritten during updates
# see https://git.ispconfig.org/ispconfig/ispconfig3/issues/4227

##################################################
##################################################
##################################################
##################################################
##################################################
##################################################
##################################################
##################################################
##################################################
##################################################
##################################################
##################################################

{

  SOURCE=$1
  URL=""
  SIG=""

  if [[ "$SOURCE" == "stable" ]]; then
    URL="https://www.ispconfig.org/downloads/ISPConfig-3-stable.tar.gz"
    SIG="https://www.ispconfig.org/downloads/ISPConfig-3-stable.tar.gz.sig"
  elif [[ "$SOURCE" == "nightly" ]]; then
    URL="https://www.ispconfig.org/downloads/ISPConfig-3-nightly.tar.gz"
  elif [[ "$SOURCE" == "git-develop" ]]; then
    URL="https://git.ispconfig.org/ispconfig/ispconfig3/-/archive/develop/ispconfig3-develop.tar.gz"
  else
    echo "Please choose an installation source (stable, nightly, git-develop)"
    exit 1
  fi

  GPGV=$(command -pv gpgv)
  KEYRING="/usr/local/ispconfig/security/trustedkeys.gpg"

  CURDIR=$PWD

  die() {
    echo "$1"
    # shellcheck disable=SC2164
    cd "$CURDIR"
    exit 1
  }

  save_umask=$(umask)
  umask 0077
  tmpdir=$(mktemp -dt "ISPConfig-update.XXXXXXXXXX")
  test $? -eq 0 || die 'mktemp failed'
  cd "$tmpdir" || die 'could not chdir into temporary working directory'
  umask "$save_umask"

  # shellcheck disable=SC2064
  trap "rm -rf \"$tmpdir\"" EXIT

  echo "Downloading ISPConfig update."
  wget -q -O ISPConfig-3.tar.gz "$URL" || die "Unable to download the update."
  if [ -n "$SIG" ] && [ -n "$GPGV" ] && [ -f "$KEYRING" ]; then
    wget -q -O ISPConfig-3.tar.gz.sig "$SIG" || die "could not download signature file"
    if "$GPGV" --quiet --keyring "$KEYRING" ISPConfig-3.tar.gz.sig ISPConfig-3.tar.gz; then
      echo "Verified the integrity of the ISPConfig update file"
    else
      die "Could not verify the integrity of the ISPConfig update file."
    fi
  fi
  echo "Unpacking ISPConfig update."
  tar xzf ISPConfig-3.tar.gz --strip-components=1
  cd install/ || die "could not chdir into install directory"
  php -q \
    -d disable_classes= \
    -d disable_functions= \
    -d open_basedir= \
    update.php

  # shellcheck disable=SC2164
  cd "$CURDIR"
  exit 0
}
