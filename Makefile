plugin = FrappeGantt
version = $(shell git describe --tags --always)

.PHONY: all archive vendor test test-sqlite lint clean

all: archive

## Build the installable plugin archive.
archive:
	@ echo "Building ${plugin} ${version}"
	@ git archive HEAD --prefix=${plugin}/ --format=zip -o ${plugin}-${version}.zip

## Rebuild the bundled Frappe Gantt files from upstream source.
vendor:
	@ tools/build-vendor.sh

## Run the unit tests. Expects the plugin to sit in a Kanboard checkout at
## plugins/${plugin}, which is how Kanboard loads it anyway.
test: test-sqlite

test-sqlite:
	@ cd ../.. && ./vendor/bin/phpunit -c tests/units.sqlite.xml plugins/${plugin}/Test/

test-postgres:
	@ cd ../.. && ./vendor/bin/phpunit -c tests/units.postgres.xml plugins/${plugin}/Test/

test-mysql:
	@ cd ../.. && ./vendor/bin/phpunit -c tests/units.mysql.xml plugins/${plugin}/Test/

## Syntax-check every PHP and JavaScript file.
lint:
	@ find . -name '*.php' -not -path './.git/*' -exec php -l {} \; | grep -v '^No syntax errors' || true
	@ node --check Assets/kanboard-gantt.js

clean:
	@ rm -f ${plugin}-*.zip
