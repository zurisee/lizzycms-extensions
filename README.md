# lizzycms
Flat-file CMS inspired by a Lizzard - elegant, nifty and agile

---

## What is Lizzy?

Stay tuned - more to come soon.

## Installation

- Start from a folder under your web-server's doc-root (let's call it 'project folder')
- Clone Lizzy into a subfolder of your project folder, name the subfolder '_lizzy/'
- Copy `_lizzy/index.php` to your project folder (i.e. one up)
- Open the project folder in your browser -> installation will be completed automatically
- Reload and you are ready to role

**Note**: this creates a skeleton website - no need to strip it first.

To add content, edit the file `pages/content.md`.

To add pages, rename `config/#sitemap.txt` to `config/sitemap.txt` and add lines. 
Each line corresponds to a page. Indent to add a hierarchy. Most often there is no need for arguments (which would be added in curly braces).

