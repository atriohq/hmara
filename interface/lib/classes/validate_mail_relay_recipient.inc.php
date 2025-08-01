<?php

class validate_mail_relay_recipient {


        function get_error($errmsg) {
                global $app;

                if(isset($app->tform->wordbook[$errmsg])) {
                        return $app->tform->wordbook[$errmsg]."<br>\r\n";
                } else {
                        return $errmsg."<br>\r\n";
                }
        }

        function check_validation_server($field_name, $field_value, $validator) {
                global $app;

                $access_type = isset($_POST['access']) ? $_POST['access'] : '';

                if($access_type === 'reject_unverified_recipient') {
                        $valServer = trim($field_value);

                        // Must not be empty
                        if($valServer === '') {
                                return $this->get_error('validation_server_empty');
                        }

                        // Regex for smtp:host or smtp:[host]:port
                        $pattern = '/^smtp:(?:\[[^\]]+\](?::\d+)?|[^:\[\]]+(?::\d+)?)$/i';
                        if(!preg_match($pattern, $valServer)) {
                                return $this->get_error('validation_server_invalid');
                        }
                }

                return ''; // No error
        }
}
