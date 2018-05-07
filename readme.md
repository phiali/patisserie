Patisserie - [Bake, Don’t Fry](http://www.aaronsw.com/weblog/000404).

##### Overview
Patisserie is a flat file static site generator with a web-based interface to manage content. Patisserie was developed as existing solutions seemed to focus either flat file static site generator or a flat file CMS. I wanted something that generated a static site while at the same time providing a web-based interface to work with content.

##### Installation

You'll need a web server configured with Apache (for .htaccess) and PHP7. Clone the repo and configure a website to point to the public folder.

Copy config/site.yaml.dist to config/site.yaml editing the file with values suitable for your site.

To login to the site head on over to http://<site>/_p/auth/login. If this is the first time follow the instructions to set up a password which will involve pasting a value into config/site.yaml

That should be all the setup you need. If you're creating future-dated content I'd recommend setting up an hourly cron job running `composer build-site`.

There's also an included Dockerfile if you'd like to start up a development environment or try the system out. Run ```docker-compose up -d``` to start and ```docker-compose stop``` when you're done.

##### Usage
All content is stored off of subfolders in the public folder. These folders map directly to the URL structure of the site so public/hello-world would be accessible as http://<site>/hello-world.

You'll have an index.md file in this folder containing YAML frontmatter for metadata followed by your content. An example might be: 

~~~

---
created_at: '2017-12-31 14:43:00 Australia/Sydney'
title: 'Goodbye 2017, Hello 2018!'
indexable: 'yes'
---

This is a sample entry.

~~~

Any additional content, such as images or files, are saved alongside index.md and served directly by the web server. The
only time PHP is involved is for requests to the admin interface over at http://<site>/_p/.

If you're editing content from the included admin interface the system will automatically parse your index.md entries generating rendered content as index.html. You're free to create content outside of the admin interface. If you do you can use the following command line tools:

 * composer index-site # This will index the content
 * composer build-site # This will build recently changed content
 * composer rebuilt-site # This will rebuild the entire site, useful after making template changes
 
If you're creating future-dated posts then you'll want to schedule the build-site command to run from time to time. You could do this with an hourly cron job for example.

##### Plugins

Patisserie supports plugins with the following hooks:

 * contentLoaded - Called when the markdown file is loaded. You could use this to work with the markdown content before it's parsed
 * contentParsed - Called when the markdown content is parsed and converted to HTML.
 * contentWritten - Called after content has been written

See the plugins off of the root where the system has plugins for:

 * Generating RSS feeds
 * Generating the archive page
 * Generating the front page

##### Micro.blog support

Way back in 2004 Matt Mullenweg (of Wordpress fame) came up with the idea of [asides](https://ma.tt/2004/05/asides/). This was a hack to enable title-less posts within Wordpress. The idea being that post titles are a great big barrier to entry - you should be able to just start writing and not have to worry about a super big title.

Patisserie has support for title-less entries called asides. They're based on their own template (templates/aside.twig) and do away with the title. The URL itself will be date-based such as /Y/m/d/His (Year/Month/Day followed by Hours, minutes and seconds).

This fits in perfectly with [micro.blog](http://micro.blog) and a default installation will generate a specific RSS feed for the service. You'll find that as /aside.feed.rss.xml and differs from the standard feed by not having titles for asides.

Asides are a great way to get a thought out there. It's Twitter but keeping control over your own content. 

##### Todo:

 * MicroPub API support
 * Some way to cater for user-defined themes/templates 