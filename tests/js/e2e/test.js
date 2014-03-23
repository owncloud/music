'use strict';

describe('E2E: Testing Routes', function() {

	beforeEach(function() {
		browser().navigateTo('index.html/#/');
	});

	it('should show root', function() {
		browser().navigateTo('/');
		expect(browser().location().path()).toBe('/');
	});

	it('should show root for undefined pathes', function() {
		browser().navigateTo('#/undefined');
		expect(browser().location().path()).toBe('/');
	});

	it('should have a working /demo route', function() {
		browser().navigateTo('#/login');
		expect(browser().location().path()).toBe('/login');
	});

});

describe('Booking', function () {

	it('should show a slot selection', function () {
		browser().navigateTo('/');
		setTimeout(function() {
			expect(element('.book-form', "Booking Form").count()).toEqual(1);
			expect(element('.book-form form[name="book_item"]', "Form for Slots Selection").count()).toEqual(1);
			expect(element('.book-form form[name="book_item"] .submit', "Add Slot Button").count()).toEqual(1);
		}, 1000);
	});

});
