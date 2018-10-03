#New Lizzy-Based Website

Congratulations! It's working.

{{ vgap(2em) }}

If you know what you are doing you are ready to go. Just start editing file ``pages/home/home.md``...

If not, visit {{ link( 'https://getlizzy.net/', type:external ) }}.

{{ vgap(4em) }}
---

$useradmin=<<<EOT

**Note**:  
You should create an admin account ASAP. BR
To do so, just enter your e-mail address below: BR

{{ user-admin( self-signup, group: admin ) }}

From now on you can login using your e-mail address (without a password). BR 

The very first user account that is created will get **admin rights**. BR
Any further accounts (typically) get **guest rights**. BR BR

If you like, you can later modify the file ``config/users.yaml`` to add a password and display name, etc.

EOT

{{ if( file:'~/config/users.yaml', op:'<', arg: 1, then:"$useradmin" ) }}

