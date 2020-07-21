Moodle WebService Manage Sections (wsmanagesections)
====================================================

This local plugin allows you to manage the section structure of a moodle course via REST API. 
You can already delete, hide and set markers via the webservice function core_course_edit_sections.
With local_wsmanagesections you can in addition
* create sections at a defined position,
* move a section to a definde position,
* update the sections names,
* update the courseformat specific section format options. 

Configuration
-------------
No configuration needed, just install the plugin. Keep in  mind to add the functions to the rest-service.

Usage
-----
Use functions over Rest API. Have a look at python sample code at https://github.com/corvus-albus/corvus-albus-moodle-local_wsmanagesections-script-examples.

Requirements
------------
- Moodle 3.3 or later

Installation
------------
Copy the wsmanagesections folder into your /local directory. Add the functions to your rest-service. 

Author
------
Corvus Albus
