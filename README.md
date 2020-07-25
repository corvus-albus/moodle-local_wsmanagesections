Moodle WebService Manage Sections (wsmanagesections)
====================================================

This local plugin allows you to manage the section structure of a moodle course via REST API. 
Via the webservice function core_course_edit_sections you can already delete and hide sections and set markers.
"Webservice mamnage sections" gives you additional functions:
* local_wsmanagesections_create_sections: create sections at a defined position,
* local_wsmanagesections_move_section: move a section to a definded position,
* local_wsmanagesections_get_sectionnames: get section names,
* local_wsmanagesections_update_sectionnames: update section names,
* local_wsmanagesections_get_sectionformatoptions: get section format options specific to the courseformat,
* local_wsmanagesections_update_sectionformatoptions: update section format options specific to the courseformat.

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
