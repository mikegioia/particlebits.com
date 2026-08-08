# Compile targets
compile:
	php compile.php dist

build:
	php compile.php build

local:
	php compile.php local

# Regenerate the privacy worksheet PDF from the compiled build output.
# Requires Google Chrome. Run `make compile` afterwards to propagate.
worksheet: build
	"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
		--headless=new --disable-gpu --no-pdf-header-footer \
		--print-to-pdf=sites/particlebits.com/articles/privacy/2018/privacy-quiz/media/privacy-worksheet.pdf \
		build/particlebits.com/media/2018/privacy-quiz/worksheet.html

# Serve the dist site at http://localhost:8000
serve:
	php -d variables_order=EGPCS -S localhost:8000 -t dist/particlebits.com router.php

# Serve the build site at http://localhost:8000
serve-build:
	php -d variables_order=EGPCS -S localhost:8000 -t build/particlebits.com router.php

# Recompile the local target every 5 seconds while writing
watch:
	watch -b -c -e -n 5 php compile.php local

.PHONY: compile build local serve serve-build watch worksheet
