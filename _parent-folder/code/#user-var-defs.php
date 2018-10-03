<?php
/*
 * user-var-defs.php
 *
 * Purpose: let's you define variables as an array of key-value tuples.
 *
 * Can be enabled separately by option config->custom_permitUserVarDefs.
 *
 * Has no access to Lizzy's objects.
 *
  * Note: to use this feature it must be enabled in config/config.yaml: custom_permitUserVarDefs: true
 */

if (file_exists('config/vars.json')) {
    $variables = json_decode(file_get_contents('config/vars.json'), true);
} else {
    $variables = [
        'myvar' => "PHP-Code '<code>user-var-defs.php</code>' says <em>Hi Lizzy</em>!",
    ];
}
return $variables;