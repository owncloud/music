# jQuery.initialize

Version: 1.1.0, Last updated: 2017-02-06

jQuery.initialize plugin is created to help maintain dynamically created elements on the
page.

## Synopsis

jQuery.initialize will iterate over each element that matches the selector and apply the
callback function. It will then listen for any changes to the Document Object Model (DOM)
and apply the callback function to any new elements inserted into to the document that
match the original selector.

      $.initialize([selector], [callback])

This allows developers to define an initialization callback that is applied whenever a new
element matching the selector is inserted into the DOM. It works for elements loaded via
AJAX also.

Simple demo - [click here](http://adampietrasiak.github.io/jQuery.initialize/test.html)

## Example of use
  
	$.initialize(".some-element", function() {
		$(this).css("color", "blue");
	});
	
 But now if new element matching .some-element selector will appear on page, it will be instantly initialized. The way new item is added is not important, you dont need to care about any callbacks etc.
  
	$("<div/>").addClass("some-element").appendTo("body"); //new element will have blue color!

## Browser Compatibility

Plugin is based on **MutationObserver**. It will works on IE9+ (**read note below**) and every modern browser.

Note: To make it work on IE9 and IE10 you'll need to add MutationObserver polyfill - like ones here: <https://github.com/webcomponents/webcomponentsjs>

-----------------
[Performance test](https://jsfiddle.net/x8vtfxtb/5/) (thanks to **@dbezborodovrp** and **@liuhongbo**)

## Todo

 - make it `bower` and `npm` compatible, add advanced performance test.

## Contributors
- Adam Pietrasiak
- Damien Bezborodov
- Ninos Ego
- Michael Hulse
