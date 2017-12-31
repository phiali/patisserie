Patisserie - [Bake, Don’t Fry](http://www.aaronsw.com/weblog/000404).

Patisserie is a file-based static site generator but also includes a web-based interface to create new content. If you
prefer you can create the content within the filesystem itself.

The index.md file contains YAML frontmatter followed by the content of the entry. An example might be: 

~~~

---
created_at: '2017-12-13 14:43:00 Australia/Sydney'
title: 'Goodbye 2017, Hello 2018!'
indexable: 'yes'
---

This is a sample entry.

~~~

Any additional content, such as images or files, are saved alongside index.md and served directly by the web server. The
only time PHP is involved is for requests to the admin interface over at http://example.org/_p/.

##### Todo:

 * XMLRPC interface
 * MicroPub API support