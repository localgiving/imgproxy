# imgproxy
An image proxy for transparent image and asset delivery.

This is set up to run in a Docker container.

rundocker.sh runs it in a local environment

To use:

http://example.com/200/http://localgiving-user-pics.s3.amazonaws.com/headshots/Kevin.jpeg
http://example.com/400,ra=15,fltr=cont-200/http://localgiving-user-pics.s3.amazonaws.com/headshots/Kevin.jpeg

Check http://phpthumb.sourceforge.net/demo/docs/phpthumb.readme.txt for more options - replace |'s with -'s basically.


Conf.ini can be used to set allowed referers to prevent hot-linking, and to serve SSL files.