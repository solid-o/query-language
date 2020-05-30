#!/usr/bin/env bash

set -e

DEBUG=${DEBUG:-0}

if [ ! -f dep/kmyacc/src/kmyacc ] ; then
    git submodule update --init --recursive
    pushd dep/kmyacc
    make
    popd
fi

DEBUG_FLAG=
if [ "${DEBUG}" == "1" -o "${DEBUG}" == "yes" ] ; then
    DEBUG_FLAG=-t
fi

dep/kmyacc/src/kmyacc ${DEBUG_FLAG} -L php -m src/Grammar/template.parser.php src/Grammar/AbstractGrammar.y
sed 's/\/\/ \$namespace/namespace Solido\\QueryLanguage\\Grammar;/' src/Grammar/AbstractGrammar.php > src/Grammar/grammar.php.tmp
vendor/bin/php-cs-fixer fix --allow-risky=yes -q src/Grammar/grammar.php.tmp
mv src/Grammar/grammar.php.tmp src/Grammar/AbstractGrammar.php
