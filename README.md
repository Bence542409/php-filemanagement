# php-storageserver
<p>PHP file management system with directory listing.</p>
<br />
<p>Built by me and ChatGPT.</p>
<p>The files are in Hungarian, but you can change it as you like.</p>
<p>If PHP is not yet installed on your web server: <a href="https://www.php.net/downloads.php" target="_blank">https://www.php.net/downloads.php</a></p>
<br />
<p>index_dir.php --></p>
<ul>
  <li>lists the files available in the directory</li>
  <li>reads the name of the directory</li>
  <li>search option</li>
  <li>opens picture, video and pdf files in browser</li>
  <li>press the download button after the filename to download (if the file is a folder it makes a .zip archive of the folder before downloading)</li>
  <li>press the "backspace" key to go to the previous page</li>
  <li>press the "enter" key to focus the search input</li>
  <li>press "enter" while in an inputarea to open the first result of the search</li>
  <li>press the "esc" key to defocus the search input</li>
  <li>press the "tab" key to reset the search input</li>
  <li>press the numeric keys (1 - 9) to open the selected result of the search</li>
  <li>press the '0' key to go to the parent directory</li>
</ul>
<p>index_include.php --></p>
<ul>
  <li>includes the original PHP page for easy copy-paste</li>
</ul>
<p>download.php --></p>
<ul>
  <li>reads the file path to a file or folder and downloads it (if the file is a folder it makes a .zip archive of the folder before downloading)</li>
</ul>
<p>admin.php --></p>
<ul>
  <li>gives you the option to upload, rename or delete files and create or delete (only empty) directorys</li>
  <li>syntax: download.php?teszt/teszt.jpg</li>
</ul>
<p>.htaccess --></p>
<ul>
  <li></li>.
</ul>
