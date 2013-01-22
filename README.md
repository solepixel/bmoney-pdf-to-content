BMoney PDF to Content
=================

~Current Version:1.0~

Actions
===========

### bmptc_thumbnail_{$conversion_key} - hook executed to create the thumbnail using one of the generators. The $conversion_key portion is the index of $this->generators array.
* var: (string url) $pdf
* var: (int) $post_id
* var: (string) $conversion_format
* var: (int) $conversion_quality



Filters
===========

### bmptc_thumbnail_generators - during init, allows you to modify thumbnail generators
* var: (array) $this->generators

### bmptc_thumbnail_formats -  - during init, allows you to modify thumbnail formats
* var: (array) $this->formats



Changelog
===========
	
### 1.0
* Initial build
