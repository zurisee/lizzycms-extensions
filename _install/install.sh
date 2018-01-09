#!/bin/sh

# Lizzy installation script
#	run it from the target folder where your web-app should reside
#

echo Lizzy has not been installed yet.

echo ""

echo "Now installing to:"
echo "  `pwd`"

echo ""

echo "  -> Copying files to root of application..."
# copy basic folders to root of app
cp -R _lizzy/_parent-folder/* .

echo ""


echo "  -> Activating .htaccess..."
# activate the .htaccess file
mv htaccess .htaccess

echo ""

echo Installation successful!

echo ""

echo Please reload the page now.
