<?php

/*
Copyright (c) 2018, Óscar Marcos (funcli.net)
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

//**** Module Definition ****

// Name of the module. The module name must match the name of the module directory.
// The module name may not contain spaces.
$module['name']      = 'vcs';

// Title of the module which is dispalayed in the top navigation.
$module['title']     = 'Version control';

// The template file of the module. This is always 'module.tpl.htm' unless
// there are any special requirements such as a three column layout.
$module['template']  = 'module.tpl.htm';

// The page that is displayed when the module is loaded.
// The path must is relative to the web/ directory
$module['startpage'] = 'vcs/web_git_list.php';

// The width of the tab. Normally you should leave this empty and
// let the browser define the width automatically.
$module['tab_width'] = '';

//****  Menu Definition ****

// Make sure that the items array is empty
$items = array();

// Add a menu item with the label 'Send message'
$items[] = array( 'title'   => 'View repositories',
                  'target'  => 'content',
                  'link'    => 'vcs/web_git_list.php'
                );

// Add a menu item with the label 'View messages'
$items[] = array( 'title'   => 'Add repository',
                  'target'  => 'content',
                  'link'    => 'vcs/web_git_edit.php'
                );

// Append the menu $items defined above to a menu section labeled 'Support'
$module['nav'][] = array( 'title' => 'Git',
                          'open'  => 1,
                          'items'	=> $items
                        );

?>