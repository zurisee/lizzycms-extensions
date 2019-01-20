# Welcome to Lizzy

Supposedly you just launched this site for the first time.

Congratulation! It works!
{{ vgap }}

## Please Note

You should create an admin account ASAP.  
To do so, you need to create the file ``config/users.yaml`` (or rename config/#users.yaml).

Create a new entry like such:

    short-name:
        password: ...       # 1)
        groups: admins
        emal: ...

^1^) The password needs to be in hashed form (i.e.bcrypt-converted).  
Use the tool below to convert your password.

**Note:** you can invoke the password converter using url-arg ``?convert`` (while logged in as admin)

{{ vgap }}

--------


{{ password-converter( false ) }}