Moodle WebService Manage Sections (wsmanagesections)
====================================================

This local plugin allows you to manage the section structure of a moodle course via REST API. 
Functions are build analog to the core_course function. 

"Webservice manage sections" gives you the following functions:
* local_wsmanagesections_create_sections: create sections at a defined position,
* local_wsmanagesections_delete_sections: delete sections with given sectionnumber or -id,
* local_wsmanagesections_move_section: move a section to a definded position,
* local_wsmanagesections_get_sections: get the settings of given sections (name,visibility, format options, ...),
* local_wsmanagesections_update_sections: update  the settings of given sections (name,visibility, format options, ...).

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
