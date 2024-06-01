# Automatic-creation-of-articles
This plugin can be used to create and delete articles in joomla with information in that article when clicking in a button in a php script.

Example of the use in the php-script:
- For deletion of an article:
```
   // Obtain the directory and the subdirectory name
		$directory = basename(dirname($dirPath));
		$subdirectory = basename($dirPath);

		// Call plugin to delete an article
		$url = JURI::root() . 'index.php?option=com_ajax&plugin=createsubdirectoryarticles&format=raw&task=deleteArticle';
		$data = array('directory' => $directory, 'subdirectory' => str_replace(' ', '_', $subdirectory));

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Custom-Header: YourCustomHeaderValue'));
		$response = curl_exec($ch);
		if (curl_errno($ch)) {
			$error_msg = curl_error($ch);
		}
		curl_close($ch);
```
- For creation of an article:
```
			// Obtain the directory and the subdirectory name
			$directory = basename(dirname($newDirPath));
			$subdirectory = basename($newDirPath);

      // Call plugin to create an article
      $url = JURI::root() . 'index.php?option=com_ajax&plugin=createsubdirectoryarticles&format=raw&task=createArticle';
      $data = array('directory' => $directory, 'subdirectory' => str_replace(' ', '_', $subdirectory));

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Custom-Header: YourCustomHeaderValue'));
      $response = curl_exec($ch);
      if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
      }
      curl_close($ch);
```
