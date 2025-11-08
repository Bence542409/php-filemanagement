# php-fileserver
<p>PHP file management system with directory listing.</p>
<br />
<p>Built by me and ChatGPT.</p>
<p>The files are in Hungarian, but you can change it as you like.</p>
<p>If PHP is not yet installed on your web server: <a href="https://www.php.net/downloads.php" target="_blank">https://www.php.net/downloads.php</a></p>
<br />
<p>php/index_dir.php --></p>
<ul>
  <li>lists the files available in the directory of the include file (filename, creation date, modification date, type, download icon)</li>
  <li>reads the path of the directory without the unnecessary parts of the url (if directory is not root)</li>
  <li>reads the name of the directory (if direcrory is root)</li>
  <li>hides itself and other system files</li>
  <li>opens picture, video, pdf and php files in browser</li>
  <li>option to browse between folders and files in the directory</li>
  <li>counts the number of files (only on desktop view)</li>
  <li>press the search-all icon or the 'K' key to search in all subdirectories (only on desktop view)</li>
  <li>press the download-folder icon or the 'L' key (while no records are selected) to download all the files in the directory (only on desktop view)</li>
  <li>press the login icon or the 'A' key to open the admin panel (only on desktop view)</li>
  <li>press the download button or the 'L' key (while records are selected) to download files or folders individually (if the file is a directory, it makes a .zip archive of the folder before downloading)</li>
  <li>press the "backspace" key to go to the previous page (while textbox is inactive)</li>
  <li>press the arrow keys to move between records</li>
  <li>press the "enter" key to focus the search input (while textbox is inactive and no records are selected)</li>
  <li>press "enter" while the textbox is active and no records are selected to open the first result of the search</li>
  <li>press "enter" while records are selected to open that specific record</li>
  <li>press the "esc" key to defocus the search input and selected records</li>
  <li>press the "esc" key to go to the parent directory (while textbox is inactive)</li>
  <li>press the "tab" key to reset the search input</li>
  <li>press the numeric keys (1 - 9) to open the selected result of the search</li>
  <li>press the '0' key to go to the parent directory</li>
</ul>
<p>php/login.php --></p>
<ul>
  <li>promts the user to login in (default password: admin)</li>
  <li>unlocks root access in search.php and download.php</li>
  <li>grants access to admin.php</li>
</ul>
<p>php/download.php --></p>
<ul>
  <li>reads the file path to a file or folder from a specific directory, and downloads it from one directory above (if the file is a directory, it makes a .zip archive of the folder before downloading)</li>
  <li>if the download resource is not in an allowed filepath, redirects to login.php (if user is not already authenitcated)</li>
</ul>
<p>php/admin.php --></p>
<ul>
  <li>redirects to login.php (if user is not already authenitcated)</li>
  <li>gives you the option to upload, rename or delete files and create or delete (only empty) directorys</li>
  <li>unzips the file after uploading, if the file is a .zip archive</li>
</ul>
<p>php/upload.php --></p>
<ul>
  <li>option to upload files anonymously to a given direcrory</li>
</ul>
<p>php/search.php --></p>
<ul>
  <li>option to search in allowed filepaths</li>
  <li>button to log in using login.php to gain root access</li>
</ul>
<p>index_include.php --></p>
<ul>
  <li>refers to the code (php/index-dir.php) using its own directory</li>
</ul>
<p>.htaccess --></p>
<ul>
  <li>redirects example.com to example.com/server1/
  <li>redirects example.com/admin to example.com/server1/php/admin.php</li>
  <li>redirects example.com/search to example.com/server1/php/search.php</li>
  <li>redirects example.com/upload to example.com/server1/php/upload.php</li>
  <li>redirects to example.com/server1/php/download.php?$1 if the URL starts with /download/</li>
  <li>redirects to example.com/server1/photo/%1/colored/ if the URL starts with a number (to work with my <a href="https://github.com/Bence542409/php-gallery">php gallery system</a>)</li>
</ul>
