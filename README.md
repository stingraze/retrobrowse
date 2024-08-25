# retrobrowse
(C)Tsubasa Kato - Inspire Search Corp. Last updated on 2024/8/18 18:53PM JST

![Sigmarion 3 running retrobrowse (canary version, index10.php)](https://github.com/stingraze/retrobrowse/blob/main/sigmarion3-nytimes-world0.jpg)

This PHP script allows modern sites (like nvidia.com, nytimes.com ) etc. to load in a minimum format. (Almost all text, some images) 

This is useful to use with old PDAs and devices with small RAM size.
The latest release is index.php

Update:8/5/2024 13:10PM JST:
C:Amie at HPC:Factor has been kind enough to contribute some code to make it work on a Windows environment.
I have not personally tested it, but it is under windows directory. (8/6/2024: deleted as it is now index.php on root directory of this repository)

Update: 8/6/2024 11:43PM JST:
I have made the C:Amie's edited version and mine mostly the same (index.php).

Update: 8/12/2024 17:21 JST:
C:Amie has been kind enough to make "How to install retrobrowse on Windows 10 / 11 under IIS"

Update: 8/25/2024 16:54 JST:
Latest version of retrobrowse has been tested to work on lynx (using lynx.scramworks.net via telnet) under DOS using HP 200LX.

Visit here: https://www.hpcfactor.com/support/cesd/200278/how_to_install_retrobrowse_on_windows_10_11_under_iis/

Important:

*You will need .htaccess for user authentication in this Ver. 0.11.2a (My updated version using C:Amie's 0.11.2)

You will need to change these lines:
```
const IMG_ROOT_DIR		= '/var/www/html/retrobrowse'; 
#Change above to below if in Windows Environment (Haven't tested yet):  
#const IMG_ROOT_DIR		= 'C:\inetpub\wwwroot';
```

If you are on Ubuntu environment, leave it as is, if on Windows environment, comment out 

```const IMG_ROOT_DIR		= '/var/www/html/retrobrowse'; " ```

and uncomment:

```#const IMG_ROOT_DIR		= 'C:\inetpub\wwwroot';```

Supported PHP Versions:
Tested to work on PHP 8.0 & PHP 8.3.9 on Ubuntu 20.04
