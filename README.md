# Alley VIP AB Test plugin
A helper package for setting up cache varying for A/B tests in the WordPress.com VIP environment.

## Assumptions
* This package assumes that you're running in a WordPress.com VIP environment or an environment set up to work like one, with the [vip-go-mu-plugins](https://github.com/Automattic/vip-go-mu-plugins) repository installed.
* This package works with VIP's cache varying functionality, which will use a segmentation cookie to determine which group a user is in for any given test. You don't need to be using VIP's cache to set and read the cookie, as long as their cache plugin is available to be loaded. However, if you're behind another full page cache that is not VIP's, the cache varying will not work.

## Set up
1. Install this package in your project and require `alley-vip-ab-test.php` as early as reasonable. In a VIP-compatible environment, `client-mu-plugins/plugin-loader.php` is an appropriate place. Otherwise a project-specific plugin might be appropriate.
2. Extend the `Test` class with a class which sets up the required properties and method. The base class is a singleton, and thus your extending class will be, as well. See an example test class below.
3. Load your custom test class after this package has loaded, and call the `set_user group()` method to establish the group that will be selected for a given user session. It may be appropriate to do so in the functions file of your theme, or at the root of a project-specific plugin. Wherever you choose to do so, instantiate before any template output has been rendered. The cache varying will be set up when you instantiate or call the `set_user_group()` method, and that must happen before any headers are sent to the browser. If your project isn't set up with an autoloader, you'll also have to require the base `Test` class. **Note:** Cache varying happens during the `'init'` action at priority `99`, so you should set the user group before that.
4. Use the `get_user_group()` method to determine which group the current user is assigned to and make decisions in the template, notify Google Analytics via a custom dimension, etc... **Note:** cache varying and group overriding happens during the `'init'` action at priority `99`, so your experiments should happen after that. This is generally fine if the experimental changes occur in your template files, but if they are happening in hooks, take note of when that hook executes.

Note - If this package is installed via composer it will include an autoloader, which will load the base class automatically when your test class is instantiated.

### An example test class

Here's an example class to implement a test which could be used to switch between two gallery layouts:

```
<?php

class Test_Gallery_Layout extends \Alley_VIP_AB_Test\Test {
	/**
	 * The name of the cache group for this test.
	 *
	 * @var string
	 */
	protected $cache_group = 'my-gallery-layout';

	/**
	 * The name of group segment A for this test.
	 *
	 * @var string
	 */
    protected $a_group_key = 'vertical';

	/**
	 * The name of group segment B for this test.
	 *
	 * @var string
	 */
	protected $b_group_key = 'horizontal';

	/**
	 * Set the group for the current user.
	 *
	 * @param array $data Optional array of data that you may want to use when setting a group.
	 */
	public function set_user_group( $data = [] ) {
		// Send approximately 50% of users to each group.
		if ( mt_rand( 1, 100 ) <= 50 ) {
			$this->user_group = $this->a_group_key;
		} else {
			$this->user_group = $this->b_group_key;
		}
	}
}

/**
 * Get the gallery layout test singleton instance.
 *
 * @return Test_Gallery_Layout.
 */
function my_test_gallery_layout() {
	return Test_Gallery_Layout::get_instance();
}
```

Having done that, you can now instantiate your test by calling `my_test_gallery_layout()` and check the user group for the current user once or many times in your templates with `my_test_gallery_layout()->get_user_group()`. This will return a string which is equal to one of the two group keys you defined in your class. This string can be used to alter the body class, change some values in the output, or even switch entire templates. The sky is the limit.

## Evaluating the test
An A/B test isn't much use if you don't have a way to evaluate the test results. Each site has a unique setup and each test may have unique metrics for success, but here's an example using Google Analytics.

1. [Create a custom dimension in your Google Analytics property](https://support.google.com/analytics/answer/2709829?hl=en#set_up_custom_dimensions). You'll need access to the Admin panel in the Google Analytics account.
2. Google Analytics will provide a tracking code snippet which tells you how to set your custom dimension. For universal analytics, it will look something like this:
```
var dimensionValue = 'SOME_DIMENSION_VALUE';
ga('set', 'dimension1', dimensionValue);
```
3. You'll need to pass the value of your test to the front end somehow. If there are already custom dimensions being set on your site you may be able to filter the output to add your new dimension. If there are none, you could use `wp_localize_script()` to attach your value to the `window` object and then set the dimension based on that `window` property.
4. Once the demension is set you can configure custom reports to evaluate traffic based on that dimension. You can also filter reports to only show a subset of dimension values.

## Overriding the group selection
You can override the selected group in one of two ways:

* An individual user can select their cache group by adding a querytring parameter to a front end request with the key `group-{$cache_group}` and the value equal to either the `$a_group_key` or `$b_group_key` from your test class. Using the example class above, you'd add `?group-my-gallery-layout=vertical` to the URL to select the vertical gallery option for yourself.
* A developer can override the cache selection for all users by setting an option in the database with an option name of `ab-select-group-{$cache_group}` and an option value of either the either the `$a_group_key` or `$b_group_key` from your test class. Using the example class above, you'd set the option name to `'ab-select-group-my-gallery-layout'` and the option value to `'vertical'` to select the vertical gallery option for all users. Users which were previously in the horizontal gallery group may need to wait 30 minutes for the cache to clear before the update is in place.

