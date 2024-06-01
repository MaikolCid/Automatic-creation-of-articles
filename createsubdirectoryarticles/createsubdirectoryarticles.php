<?php
// no direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Categories\Category;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Log\Log;

class PlgSystemCreateSubdirectoryArticles extends CMSPlugin
{
    public function onAfterInitialise()
    {
        $app = Factory::getApplication();
        $input = $app->input;

        // Check if it's an AJAX request
        if ($input->getCmd('option') !== 'com_ajax' || $input->getCmd('plugin') !== 'createsubdirectoryarticles') {
            return;
        }

        // Check for custom header
        $customHeader = $_SERVER['HTTP_X_CUSTOM_HEADER'] ?? '';
        if ($customHeader !== 'YourCustomHeaderValue') {
            Log::add('Invalid custom header', Log::ERROR, 'createSubdirectoryArticles');
            echo "<p>Invalid custom header</p>";
            return;
        }

        $task = $input->get('task');
        $directory = $input->get('directory');
        $subdirectory = $input->get('subdirectory');

        if ($task == 'createArticle') {
            // Log received parameters
            Log::add("Received parameters - Directory: $directory, Subdirectory: $subdirectory", Log::INFO, 'createSubdirectoryArticles');
            echo "<p>Received parameters - Directory: $directory, Subdirectory: $subdirectory</p>";

            $this->createArticle($directory, $subdirectory);
        } elseif ($task == 'deleteArticle') {
            // Log received parameters
            Log::add("Received parameters for deletion - Directory: $directory, Subdirectory: $subdirectory", Log::INFO, 'createSubdirectoryArticles');
            echo "<p>Received parameters for deletion - Directory: $directory, Subdirectory: $subdirectory</p>";

            $this->deleteArticle($directory, $subdirectory);
        }
    }

    private function createArticle($directory, $subdirectory)
    {
        $subdirectory = str_replace('_', ' ', $subdirectory); // Replace underscores with spaces
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $db = Factory::getDbo();

        try {
            // Load the necessary tables
            Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/tables');

            // Get the category ID for the directory
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('title') . ' = ' . $db->quote($directory));
            $db->setQuery($query);
            $categoryId = $db->loadResult();

            if (!$categoryId) {
                // Create the category if it does not exist
                $category = new Category($db);
                $category->setLocation(1, 'last-child');
                $category->title = $directory;
                $category->alias = $directory;
                $category->extension = 'com_content';
                $category->published = 1;
                $category->access = 1;
                $category->language = '*';
                if (!$category->save()) {
                    throw new Exception($category->getError());
                }
                $categoryId = $category->id;
                Log::add("Created new category for Directory: $directory", Log::INFO, 'createSubdirectoryArticles');
                echo "<p>Created new category for Directory: $directory</p>";
            }

            // Check if an article with the same name already exists in the category
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('title') . ' = ' . $db->quote($subdirectory))
                ->where($db->quoteName('catid') . ' = ' . $db->quote($categoryId));
            $db->setQuery($query);
            $existingArticleId = $db->loadResult();

            if ($existingArticleId) {
                throw new Exception("An article with the name '$subdirectory' already exists in the category '$directory'.");
            }

            // Create the article with all necessary fields
            $article = new stdClass();
            $article->title = $subdirectory;
            $article->alias = str_replace(' ', '-', strtolower($subdirectory)); // This is okay as alias should not contain spaces
            $article->catid = $categoryId;
            $article->state = 1;

            // Adjust introtext based on category name
            $introtextBase = 'multimedia';
            if (in_array($directory, ['Tecnica', 'Construcciones'])) {
                $introtextBase = 'varios';
            }
            $article->introtext = '<p>{gallery}' . $introtextBase . '/' . $directory . '/' . $subdirectory . '{/gallery}</p>';

            $article->fulltext = ''; // Ensure the fulltext field is provided
            $article->created = Factory::getDate()->toSql();
            $article->created_by = $user->id;
            $article->modified = $article->created; // Set modified to created date if not provided
            $article->modified_by = $user->id;
            $article->images = '{}';
            $article->urls = '{}';
            $article->attribs = '{}';
            $article->version = 1;
            $article->ordering = 0;
            $article->metakey = '';
            $article->metadesc = '';
            $article->access = 1; // Public access
            $article->hits = 0;
            $article->metadata = '{}';
            $article->featured = 0;
            $article->language = '*'; // All languages
            $article->note = '';

            if (!$db->insertObject('#__content', $article)) {
                throw new Exception($db->getErrorMsg());
            }

            // Get the article ID
            $articleId = $db->insertid();

            // Add an entry to the workflow associations table
            $query = $db->getQuery(true);
            $columns = ['item_id', 'stage_id', 'extension'];
            $values = [$db->quote($articleId), 1, $db->quote('com_content.article')];
            $query
                ->insert($db->quoteName('#__workflow_associations'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));
            $db->setQuery($query);
            $db->execute();

            Log::add('Article created successfully', Log::INFO, 'createSubdirectoryArticles');
            echo "<p>Article created successfully</p>";
        } catch (Exception $e) {
            Log::add('Failed to create article: ' . $e->getMessage(), Log::ERROR, 'createSubdirectoryArticles');
            echo "<p>Failed to create article: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    private function deleteArticle($directory, $subdirectory)
    {
        $subdirectory = str_replace('_', ' ', $subdirectory); // Replace underscores with spaces
        $app = Factory::getApplication();
        $db = Factory::getDbo();

        try {
            // Load the necessary tables
            Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/tables');

            // Get the category ID for the directory
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('title') . ' = ' . $db->quote($directory));
            $db->setQuery($query);
            $categoryId = $db->loadResult();

            if (!$categoryId) {
                throw new Exception("Category for directory '$directory' not found.");
            }

            // Find the article to delete
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('title') . ' = ' . $db->quote($subdirectory))
                ->where($db->quoteName('catid') . ' = ' . $db->quote($categoryId));
            $db->setQuery($query);
            $articleId = $db->loadResult();

            if (!$articleId) {
                throw new Exception("Article '$subdirectory' not found in category '$directory'.");
            }

            // Delete the article
            $articleTable = Table::getInstance('Content', 'JTable');
            if (!$articleTable->delete($articleId)) {
                throw new Exception($articleTable->getError());
            }

            // Delete the workflow association
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__workflow_associations'))
                ->where($db->quoteName('item_id') . ' = ' . $db->quote($articleId))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content.article'));
            $db->setQuery($query);
            $db->execute();

            Log::add("Article '$subdirectory' in category '$directory' deleted successfully", Log::INFO, 'createSubdirectoryArticles');
            echo "<p>Article '$subdirectory' in category '$directory' deleted successfully</p>";
        } catch (Exception $e) {
            Log::add('Failed to delete article: ' . $e->getMessage(), Log::ERROR, 'createSubdirectoryArticles');
            echo "<p>Failed to delete article: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

