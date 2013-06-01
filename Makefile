app_name=media
build_directory=build/
package_name=$(build_directory)$(app_name)

all:
	# compile the coffeescript
	cd js; make


clean:
	rm -rf $(build_directory)


dist: clean
	mkdir -p $(build_directory)
	git archive HEAD --format=zip --prefix=$(app_name)/ > $(package_name).zip


# tests
test: javascript-tests unit-tests integration-tests acceptance-tests

unit-tests:
	phpunit tests/unit


integration-tests:
	phpunit tests/integration


acceptance-tests:
	cd tests/acceptance; make headless


javascript-tests:
	cd js; make test
