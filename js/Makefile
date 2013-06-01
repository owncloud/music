firefox_bin=/usr/bin/firefox
chrome_bin=/usr/bin/chromium
grunt=$(CURDIR)/node_modules/grunt-cli/bin/grunt
phantomjs=$(CURDIR)/node_modules/phantomjs/bin/phantomjs

all: build

build: deps
	mkdir -p $(CURDIR)/public
	$(grunt) --config $(CURDIR)/Gruntfile.js build

deps:
	cd $(CURDIR)/
	npm install --deps

watch: build
	$(grunt) --config $(CURDIR)/Gruntfile.js watchjs

karma: deps
	export CHROME_BIN=$(chrome_bin) && export FIREFOX_BIN=$(firefox_bin) && \
	$(grunt) --config $(CURDIR)/Gruntfile.js testjs

test: deps
	export PHANTOMJS_BIN=$(phantomjs) && \
	$(grunt) --config $(CURDIR)/Gruntfile.js ci

phpunit: deps
	$(grunt) --config $(CURDIR)/Gruntfile.coffee testphp

clean:
	rm -rf $(CURDIR)/node_modules/
	rm -rf $(CURDIR)/test-results.xml