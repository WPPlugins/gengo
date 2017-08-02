=== Gengo ===
Contributors: majelbstoat, pixline
Tags: gengo, multilingual, multiple languages, translation, language, i18n, l10n, multilanguage,
Donate link: http://jamietalbot.com/wp-hacks/forum/comments.php?DiscussionID=22
Requires at least: 2.5
Tested up to: 2.6-bleeding2
Stable tag: 2.5.3

Gengo is a full featured plugin that provides multi-language blogging for WordPress 2.5+. 

== Description ==

Gengo is a full featured plugin that provides multi-language blogging for WordPress 2.5+. It allows for an unlimited number of translations and summaries for any post and provides template tags to display language information.  It allows you to edit translations side by side, detects and filters by language automatically when a visitor comes to your site and automatically generates semantic information for links and content blocks.  It is configurable via an options page.

Gengo is mainly written by [Jamie Talbot](http://jamietalbot.com/), with some help from [Paolo Tresso (aka Pixline)](http://pixline.net/en/) and the community.

**More info & Support**

* [WordPress Multilingual Blog](http://wp-multilingual.net/)
* [Support Forum](http://jamietalbot.com/wp-hacks/forum/)
* [Issue Tracker](http://dev.wp-plugins.org/report/9?COMPONENT=gengo)
* [Commit Log](http://dev.wp-plugins.org/log/gengo/)

== Installation ==

1. Copy the gengo directory in the zip file to wp-content/plugins/
2. Activate the plugin on the plugin screen.
3. Add the languages you wish to use on the configuration screen.  The first language you add will be the default language, and all existing posts will be marked as being written in this language.  For English, use the locale en_US, which is WordPress' default.
4. Change your wp-config.php file by hand so that WPLANG is the locale of your default language.
5. If a Gengo localisation .mo file is available for your language, also add this to the /wp-content/languages/ directory.

DON'T FORGET STEP 4!

**Upgrading**

1. Deactivate ALL plugins before doing anything.
2. Upgrade your WordPress to the latest version (if available)
3. Upgrade Gengo files under wp-content/plugins/gengo/
4. Login, and activate the plugin.

== Frequently Asked Questions ==

= Does Gengo delete any of my data? =

No.  All of Gengo's information is stored in separate tables.  The furthest you can go is to delete all posts marked as a translation of the current post, but you won't be able to do that if you don't have permissions.

= How do I add / edit / delete a language =

Go to Options-&gt;Gengo and follow the instructions there.  It should be fairly self-explanatory.

= How do I mark a post as being a translation? =

In the sidebar on the post edit screen, click "This is a translation" and then select the post or group that this post is a translation of.  Note that you can only choose to make a post a translation of a post in another language.  Note also that you can't join a translation group for which a post in that language already exists.  Summaries for the group or post it is a translation of will automatically be transferred to this post when you Save.

This also applies to pages, of course.

= How do I write a summary? =

On the post screen, in the Gengo section of the sidebar, click "View Summaries", select the language you wish to make a summary in, and click Add Summary.  Edit the summary in the box that appears.  IMPORTANT:  This summary is not saved until you click "Update Summary" underneath the summary box.  Summaries are not updated when you click "Save", "Save and Continue Editing" or "Publish".  The summary you add will appear in the summaries block in the Gengo sidebar.  You cannot write a summary for a post that has not been saved.  You cannot add a summary if you are changing the translation option for a post or changing the language.  You can only add one summary in any language for each post or translation group.  (More than one summary in English of the same post doesn't make sense, for example).

= How do I edit a summary? =

On the post screen, in the Gengo sidebar, find the summary you wish to edit and click on the edit link.  Edit as you wish and click "Update Summary".  Your summary will be updated in the sidebar to reflect your changes.

= How do I delete a summary? =

On the post screen, in the Gengo sidebar, find the summary you wish to delete and click on the delete link.  Confirm your choice and the post will be deleted.  Mass editing of summaries will be in a future version.

= How do I do side by side translating? =

On the post screen, in the Gengo section of the sidebar, click "View Translations", "This is a translation" and then either click on the link for the post you wish to edit, underlined in a translation group, or select from the dropdown box of post translation options.  Edit your translation as you wish.  IMPORTANT:  Edits to the translation are not saved until you click "Update Translation" underneath the translation box.  Translations are not updated when you click "Save", "Save and Continue Editing" or "Publish".

= I made a mistake and marked an entry as a translation when it wasn't! =

No problem, simply uncheck the "This is a translation" checkbox or select a different post or group to join and click "Save".  Note that removing a post from a translation group will unlink all of the summaries associated with that post.  If you are joining a new group, the post will become associated with that group's summaries, if any exist.  To double check that this is ok, make sure the "View Summaries" checkbox is ticked.  This will show you how altering the translation group will affect this post's summaries.

= I wrote a post with a summary and now I want to mark it as a translation of another post, but Gengo says I will lose the summary I just wrote! =

Whenever you change the summary group of a post, you lose the summaries associated with it.  The solution in this instance is to mark the *other* post as a translation of this post, wherein both will pickup this summary.

= Why can I only see a few translation groups to choose from in the sidebar? =

This is so that your sidebar doesn't grow to ridiculous lengths when you have lots of translation groups.  Use the buttons on the sidebar to cycle through available groups.  The default maximum of groups at one time is 3, plus the existing group, if it exists.  You can change this maximum by editing GENGO_GROUPS_LIMIT in gengo.php.  A similar restriction applies to the dropdown list of available posts, to prevent the listing of every single post.  The default number for this is 10.  You can change this maximum by editing GENGO_POSTS_LIMIT in gengo.php.

= It doesn't translate for me! =

That's right, it doesn't.

= How can I set my default language? =

Administrators can set the blog default language on the Gengo Options page.  Users can set their personal default language on the profile page.  You can see an overview of this information in the activity box on the dashboard.

= What is reading in multiple languages? =

It means just that.  Some of your visitors will be bilingual and able to read your posts in more than one language.  Multilanguage reading allows them to see all the content they can comprehend, showing your theme in their primary language.  Gengo arranges the filtering so that posts are not duplicated in multiple languages and will autodetect their viewable languages from browser settings when they arrive for the first time.  The gengo_language_control(); template function allows your visitors to add or remove readable languages, as required.

= How can I translate categories? =

Enter the synonyms you want on the Gengo-&gt;Category Synonyms admin page.

= I want to do different stuff in my theme for different languages. =

Use is_language() with the code of the language you want to check for, which will return true if that language is being displayed. Example:

&lt;?php 
	if (is_language('en')) echo "You are reading in English";
	elseif (is_language('ja')) echo "You are reading in Japanese";
	else echo "You are seeing all posts...";
?&gt;

This is a bad example though, because you can get the language the user will be looking at just by calling  the_language(). Example:

&lt;?php echo "You are reading: "; the_language(); ?&gt;

or:

&lt;?php echo "You are reading: " . the_language(true); ?&gt;

= Why is there an extra &lt;p style="clear: both"&gt; element in the the_summaries() div block? =

This is so that you can float right the list of links to other summary languages if you want to.  An example of this is at <a href="http://wp-multilingual.net">WordPress Multilingual</a>.  If this causes problems with your theme, you can safely delete it.  To customise this div, use the id and classes provided and do some CSS.

#gengo_summaries_container: The container div of the summaries.

.gengo_summary_inner: The paragraph for each summary.

#gengo_summaries_title: The legend for the summary group.

= What semantic information does Gengo add? =

It adds:

A meta content-language tag to the head of single language pages.
link rel tags for all of the translations of a post.
a href-lang tags for all links that will go to a language page.

= Where can I see it in action? =

You can see an example of Gengo in use, managing many languages at <a href="http://wp-multilingual.net">Wordpress Multilingual</a>.

= Why aren't my posts surrounded by divs specifying the language anymore? =

This was always a bit dodgy and could potentially screw up themes, so it was removed.  You can add a lang or xml:lang attribute back in to your themes yourself using the_language_code().

= I get "Fatal error: cannot instantiate non-existant class cachedfilereader" =

You have to set WPLANG in your wp-config.php file to be the default language locale.

= I get database errors like "BLOB/TEXT cannot have default value" when I activate Gengo =

You are running MySQL 5.0 in strict mode.  Find the 3 lines in gengo_schema.php and remove the "default ''".  Reinstalling Gengo will fix the problem.  This will be fixed in 0.9.1.

= I get PHP errors complaining about problems with currentuser or $wp_rewrite =

Another plugin you are running is incorrectly localised, which is causing this problem.  See http://jamietalbot.com/wp-hacks/forum/comments.php?DiscussionID=3&Focus=31#Comment_31 for more information and how to fix this.

= Fancy permalinks don't always work perfectly =

They should do as of version 0.2, so if they don't send me a detailed report and I'll look into it.

= The locale only changes the second time I view a specific language =

This was fixed in 0.2

= I sometimes get infinite redirects when Gengo isn't appending urls with language codes =

This should hopefully be fixed in 0.81.  It's a hard problem to solve, so if it's still broken, be sure to let me know in the forums.

= Gengo breaks my XHTML validation for 1.1 or 1.0 Strict =

It shouldn't do anymore.  version 0.3 appended links with &amp;, instead of relying on add_query_arg() which may or may not get fixed.

= It's pretty slow sometimes... =

If you mean after saving from the Edit Post screen, this probably isn't Gengo's fault.  Try removing pingomatic from the Update Services menu to speed up.  If saving a post times out because of pingomatic or any other update service, there is a chance that your Translation options will not be saved.  If this happens, you can just link the translations again and save again.  Best to disable pingomatic until its problems have been resolved though, I think.

= What does Gengo mean? =

It means 'language' in Japanese.

= Why are you obsessed with Japanese? =

I'm not obsessed, but I did live in Japan, so...

== Known Issues ==

= What happens if Javascript / XHTMLHTTP isn't available? = 

It won't work, sorry.  A large portion of the admin code relies on these technologies to update information in the background.  There's no way around this.

= It doesn't change the page items like "Categories", "Links" etc =

You need a specially internationalised theme for this to work.  In essence, the theme has to use _e() and __() functions around its text and provide a localisation .mo file.  Contact your theme vendor :-D

= Previous and next links go to posts in other languages! =

I know.  WordPress won't let me get at those just yet.  It's too much of a hassle to rewrite all that code, so you'll just have to live with it for the time being, I'm afraid.  I've submitted a patch to the core which would allow this to happen, so hopefully it will make it into WordPress 2.1...  It's at http://trac.wordpress.org/ticket/2415/ , where you can find out the status of the ticket and leave a comment to support its integration if you think it will be useful.  You can also apply the patch yourself if you know what you're doing.  As soon as it is patched, Gengo will take advantage, with no further modifications.

= I entered synonyms for my categories but they only show up in certain places.  In others, the default is always displayed. =

This is because WordPress applies the filter that Gengo uses inconsistently.  I've added a ticket and patch to the core which hopefully will make it into WordPess 2.1.  You can check the status of this ticket and leave a comment to add your support if you think it's important at http://trac.wordpress.org/ticket/2466/ In the meantime, if you know what you're doing, you can patch your files yourself using the patch there.  In 2.0.4 this should be a little better than before.

= The translation page doesn't do anything =

0.5 onwards provides some translation group management away from the write post screen.  If you need more, let me know.

= I get insert errors with MySQL 5.x =

This happened in strict mode, and should be fixed in 0.8

= The language tag is set incorrectly for feeds. =

This should have been fixed from 0.81 onwards.  However, for feeds for two or more languages, only the reader's first specified language is used, as there is no facility for specifying multiple languages for RSS.  This will be updated if and when WordPress supports a feed that understands multiple languages.

= My Save buttons disappear forever when changing the language of a post. =

This seems to be a browser related problem, especially some versions of Firefox.  Make sure the Firebug extension is not snooping XMLHTTP requests as this is known to cause problems.  Upgrading to the latest version of your browser should fix the problem.  Gengo is known to have problems on some installations of Firefox 1.5 but works seems to work successfully with Firefox 1.5.0.2.

= There are multiple UNIQUE indices on the wp_languages table. =

This seems to be because of a bug in WordPress' supplied upgrade function dbdelta().  This should be fixed now.

= I have another request / bug / optimisation =

Please let me know in the forums at http://jamietalbot.com/wp-hacks/forum/ .  I'll see what I can do to fix it.

= Does Gengo work with WordPress x.x.x? =

Gengo 2.5 only works with WordPress 2.5+. Version 0.9 works with WordPress 2.1/2.2, and 0.81 works with 2.0.1, but not 2.1.

= I don't have an option to view in multiple languages =

Reading in multiple languages is only supported for MySQL versions 4.1 and above.  If your MySQL is lower than that, the option won't appear.  You really should start bugging your host for a better MySQL version - WordPress 2.2 is only going to support MySQL 4.1+.

== Template Tags ==

Gengo provides a number of template functions which are very similar to those in WordPress:

* `gengo_list_languages()` - outputs a formatted lists of languages defined for this blog. Surround the call to this function with &lt;ul&gt; tags.
* `gengo_link_pages()` - replacement for `wp_link_pages()`.
* `gengo_next_posts_link()` - replacement for `next_posts_link()`.
* `gengo_previous_posts_link()` - replacement for `previous_posts_link()`.
* `gengo_snippet()` - allows you to insert small, translated blocks of text.
* `gengo_trackback_url()` - replaces `trackback_url()`.
* `gengo_viewing_languages()` - outputs a list of languages that the user is currently viewing, with js links to change priority.
* `gengo_available_languages()` - outputs a list of languages that the user isn't reading in, but are also available.
* `gengo_language_set()` - outputs save and reset to store reading options.
* `gengo_language_control()` - combines the previous 3 functions.
* `gengo_home_url()` - the home url appended with the current viewing language.
* `is_language()` - tests we are currently viewing a language.
* `the_language()` - outputs or returns the language for a page where only one language is being used.  Outputs by default.  To return the language as a string, call `the_language(true)`.
* `the_language_code()` - outputs or returns the current language code.  Outputs by default.  To return the code as a string, call `the_language_code(true)`.
* `the_language_locale()` - outputs or returns the locale for a post.
* `the_language_direction()` - outputs rtl or ltr.
* `the_viewable_languages()` - outputs or returns the languages the user is reading in.
* `the_viewable_codes()` - outputs or returns the languages the user is reading in.
* `the_summaries()` - outputs a div with a javascript switcher of all of the summaries for this post, or nothing.
* `the_translations()` - outputs a list of translations for this post, or "No Translations".
* `the_translations_comments()` - outputs a list of links to the comments sections of translations for this post.  Use in comments.php.
