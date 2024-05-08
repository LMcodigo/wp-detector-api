<?php
<<<<<<< HEAD
require 'database_read.php';
require 'find_version.php';
require 'find_theme_banner.php';
require 'database_connection.php';
require 'database_write.php';

function find_themes($html, $wpContent, $url) {
    // Returns a list of all the themes detected in the html content

    // $theme1 = [
    //     'banner' => 'https://generatepress.com/wp-content/themes/generatepress/screenshot.png',
    //     'title' => 'GeneratePress',
    //     'author' => 'Tom Usborne',
    //     'version' => '3.4.0',
    //     'website' => 'https://generatepress.com',
    //     'sanatizedWebsite' => 'generatepress.com',
    //     'reqWpVersion' => '5.2',
    //     'testedWpVersion' => '6.3',
    //     'reqPhpVersion' => '5.6',
    //     'description' => 'GeneratePress is a lightweight WordPress theme built with a focus on speed and usability. Performance is important to us, which is why a fresh GeneratePress install adds less than 10kb (gzipped) to your page size. We take full advantage of the block editor (Gutenberg), which gives you more control over creating your content. If you use page builders, GeneratePress is the right theme for you. It is completely compatible with all major page builders, including Beaver Builder and Elementor. Thanks to our emphasis on WordPress coding standards, we can boast full compatibility with all well-coded plugins, including WooCommerce. GeneratePress is fully responsive, uses valid HTML/CSS, and is translated into over 25 languages by our amazing community of users. A few of our many features include 60+ color controls, powerful dynamic typography, 5 navigation locations, 5 sidebar layouts, dropdown menus (click or hover), and 9 widget areas. Learn more and check out our powerful premium version at generatepress.com',
    //     'link' => 'https://generatepress.com/?utm_source=wp-detector',
    // ];

    // return [$theme1];

    if($url===null) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html); 
        $themes = [];

        $elements = array_merge(
            iterator_to_array($dom->getElementsByTagName('link')),
            iterator_to_array($dom->getElementsByTagName('script')),
            iterator_to_array($dom->getElementsByTagName('meta'))
        );

        foreach ($elements as $element) {

            $themes = process_element($element, $wpContent, $themes);
        }
    }
    else {
        $conn = open_database_connection();
        $stmt = $conn->prepare("SELECT wp FROM websites WHERE url = ?");
        $stmt->bind_param("s", $url);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $slugs = $row['themes'];
        }
        $slugs = str_replace(' ', '', $slugs);
        $themeSlugs = explode(',', $slugs);
        $themes = themes_from_database($themeSlugs);
    }

    return $themes;
}

function themes_from_database($themeSlugs){
    foreach ($themeSlugs as $themeSlug) {
        $retrievedData = getDataBySlug('themes', $themeSlug);
        $newTheme = [
            'author' => $retrievedData['author'],
            'link' => $retrievedData['link'],
            'website' => $retrievedData['website'],
            'sanatizedWebsite' => $retrievedData['sanatizedWebsite'],
            'description' => $retrievedData['description'],
            'title' => $retrievedData['title'],
            'reqWpVersion' => $retrievedData['reqWpVersion'],
            'testedWpVersion' => $retrievedData['testedWpVersion'],
            'reqPhpVersion' => $retrievedData['reqPhpVersion'],
            'version' => $retrievedData['version'],
            'banner' => $retrievedData['banner']
        ];
        $themes[] = $newTheme;
        $result = updateTimesAnalyzed('themes',$themeSlug);
    }
}

function process_element($element, $wpContentPath, $themes) {
    $href = $element->getAttribute('href');
    $src = $element->getAttribute('src');
    $content = $element->getAttribute('content');
    $matches = [];

    if (strpos($href, '/wp-content/themes/') !== false || strpos($src, '/wp-content/themes/') !== false || strpos($content, '/wp-content/themes/') !== false) {
        $pattern = '/\/wp-content\/themes\/(.*?)\//';

        if (preg_match($pattern, $href, $matches) || preg_match($pattern, $src, $matches) || preg_match($pattern, $content, $matches)) {
            $themeSlug = $matches[1];

            if (!in_array($themeSlug, $themes)) {
                $themePath = $wpContentPath . 'themes/' . $themeSlug . '/style.css';
                
                if (slugExists('themes', $themeSlug)) {
                        
                        $retrievedData = getDataBySlug('themes', $themeSlug);
                        if ($retrievedData !== null) {
                            if($retrievedData['banner'] === null) {
                                $themeImage = find_theme_banner($wpContentPath . 'themes/' . $themeSlug, $themeSlug);
                            }
                            $newTheme = [
                                'author' => $retrievedData['author'],
                                'link' => $retrievedData['link'],
                                'website' => $retrievedData['website'],
                                'sanatizedWebsite' => $retrievedData['sanatizedWebsite'],
                                'description' => $retrievedData['description'],
                                'title' => $retrievedData['title'],
                                'reqWpVersion' => $retrievedData['reqWpVersion'],
                                'testedWpVersion' => $retrievedData['testedWpVersion'],
                                'reqPhpVersion' => $retrievedData['reqPhpVersion'],
                                //'version' => find_version($pluginPath, 'plugin'),
                                'version' => $retrievedData['version'],
                                'banner' => $retrievedData['banner'] ?? $themeImage
                            ];
                            $themes[] = $newTheme;
                            $result = updateTimesAnalyzed('themes',$themeSlug);
                        }
                } else {
                    $themeDetails = parse_theme_info($themePath);
                    $themeImage = find_theme_banner($wpContentPath . 'themes/' . $themeSlug, $themeSlug);
                    $link = $themeDetails['link'];
                    $parsed_url = parse_url($link);
                    $sanatizedWebsite = isset($parsed_url['host']) ? $parsed_url['host'] : '';
                    $website = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
                    $website .= isset($parsed_url['host']) ? $parsed_url['host'] : '';

                    $newTheme = [
                        'author' => $themeDetails['author'] ?? null,
                        'link' => $link,
                        'website' => $website,
                        'sanatizedWebsite' => $sanatizedWebsite,
                        'description' => $themeDetails['description'] ?? null,
                        'title' => $themeDetails['title'] ?? null,
                        'reqWpVersion' => $themeDetails['reqWpVersion'] ?? null,
                        'testedWpVersion' => $themeDetails['testedWpVersion'] ?? null,
                        'reqPhpVersion' => $themeDetails['reqPhpVersion'] ?? null,
                        'version' => $themeDetails['version'] ?? null,
                        'banner' => $themeImage
                    ];
                    $themes[] = $newTheme;

                    $newTheme['slug'] = $themeSlug;
                    $newTheme['times_analyzed'] = 1;
                    $result = setDataBySlug('themes', $newTheme);
=======

require_once 'database_connection.php';

// Returns all the themes of the given url
function find_themes($links)
{
    $themes = [];

    $db = new Database();
    $db->connect();

    foreach ($links as $link) {
        if (preg_match('/.*\/themes\/([^\/]*)/', $link, $matches)) {
            $themeSlug = $matches[1];

            // Parse the URL to get the scheme and host
            $parsedUrl = parse_url($link);
            $rootDomain = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            $themePath = $rootDomain . '/wp-content/themes/' . $themeSlug;

            if (!array_key_exists($themeSlug, $themes) && preg_match('/^[a-z\-]+$/', $themeSlug)) {
                $themeInfo = get_theme_info($db, $themeSlug, $themePath);
                if (!empty($themeInfo)) {
                    $themes[$themeSlug] = $themeInfo;
>>>>>>> upstream/main
                }
            }
        }
    }

    $db->close();

    return $themes;
}

// Returns the theme information of a given theme slug
function get_theme_info($db, $themeSlug, $themePath)
{
    $result = $db->query("SELECT * FROM themes WHERE slug = '$themeSlug'");
    $row = $result->fetch_assoc();

    if (empty($row)) {
        //$themeInfo = find_theme_info_in_directory($themeSlug);
        //if (empty($themeInfo)) {
        $themeInfo = find_theme_info_in_website($themeSlug, $themePath);
        //}
        //if (empty($themeInfo)) {
        //    return null;
        //}

        $screenshot = $themeInfo['screenshot'];
        $title = $themeInfo['title'];
        $author = $themeInfo['author'];
        $version = $themeInfo['version'];
        $website = $themeInfo['website'];
        $sanatizedWebsite = $themeInfo['sanatizedWebsite'];
        $lastUpdated = $themeInfo['lastUpdated'];
        $activeInstallations = $themeInfo['activeInstallations'];
        $reqWpVersion = $themeInfo['reqWpVersion'];
        $testedWpVersion = $themeInfo['testedWpVersion'];
        $reqPhpVersion = $themeInfo['reqPhpVersion'];
        $description = $themeInfo['description'];
        $link = '';

        // Insert the theme info into the database
        $db->query("INSERT INTO themes (slug, screenshot, title, author, version, website, sanatizedWebsite, lastUpdated, activeInstallations, reqWpVersion, testedWpVersion, reqPhpVersion, description, link, timesAnalyzed, lastAnalyzed) VALUES ('$themeSlug', '$screenshot', '$title', '$author', '$version', '$website', '$sanatizedWebsite', '$lastUpdated', '$activeInstallations', '$reqWpVersion', '$testedWpVersion', '$reqPhpVersion', '$description', '$link', 1, NOW())");

    } else {
        $themeInfo = [
            'screenshot' => $row['screenshot'],
            'title' => $row['title'],
            'author' => $row['author'],
            'version' => $row['version'],
            'website' => $row['website'],
            'sanatizedWebsite' => $row['sanatizedWebsite'],
            'lastUpdated' => $row['lastUpdated'],
            'activeInstallations' => $row['activeInstallations'],
            'reqWpVersion' => $row['reqWpVersion'],
            'testedWpVersion' => $row['testedWpVersion'],
            'reqPhpVersion' => $row['reqPhpVersion'],
            'description' => $row['description'],
            'link' => $row['link'],
        ];

        // Update timesAnalyzed and lastAnalyzed
        $db->query("UPDATE themes SET timesAnalyzed = timesAnalyzed + 1, lastAnalyzed = NOW() WHERE slug = '$themeSlug'");    
    }

    return $themeInfo;
}

// Returns the theme information in the wordpress directory given a theme slug
function find_theme_info_in_directory($themeSlug)
{
    require_once 'get_content.php';
    $directoryUrl = 'https://wordpress.org/themes/' . $themeSlug;
    $directoryContent = get_content($directoryUrl);

    if (empty($directoryContent)) {
        return null;
    }

    preg_match('/<h2 class="theme-name entry-title">(.*?)<\/h2>/', $directoryContent, $matches);
    $title = $matches[1] ?? $themeTitle;

    preg_match('/<p class="theme_homepage"><a href="(.*?)">Theme Homepage<\/a><\/p>/', $directoryContent, $matches);
    $website = $matches[1] ?? null;

    $sanatizedWebsite = str_replace(['http://', 'https://'], '', $website);

    preg_match('/<div class="theme-author">By <a href="\/themes\/author\/.*?\/"><span class="author">(.*?)<\/span><\/a><\/div>/', $directoryContent, $matches);
    $author = $matches[1] ?? "No author found";

    preg_match('/<p class="version">Version: <strong>(.*?)<\/strong><\/p>/', $directoryContent, $matches);
    $version = $matches[1] ?? null;

    preg_match('/<p class="updated">Last updated: <strong>(.*?)<\/strong><\/p>/', $directoryContent, $matches);
    $lastUpdated = $matches[1] ?? null;

    preg_match('/<p class="active_installs">Active Installations: <strong>(.*?)<\/strong><\/p>/', $directoryContent, $matches);
    $activeInstallations = $matches[1] ?? null;

    preg_match('/<p class="requires">WordPress Version: <strong>(.*?) or higher<\/strong><\/p>/', $directoryContent, $matches);
    $reqWpVersion = isset($matches[1]) ? $matches[1] . ' or higher' : null;

    preg_match('/<p class="requires_php">PHP Version: <strong>(.*?) or higher<\/strong><\/p>/', $directoryContent, $matches);
    $reqPhpVersion = isset($matches[1]) ? $matches[1] . ' or higher' : null;

    preg_match('/<div class="theme-description entry-summary"><p>(.*?)<\/p><\/div>/', $directoryContent, $matches);
    $description = trim($matches[1] ?? "No description provided");

    preg_match('/<img src="(.*?)" srcset/', $directoryContent, $matches);
    $screenshot = $matches[1] ?? "";

    $theme = [
        'screenshot' => $screenshot,
        'title' => $title,
        'author' => $author,
        'version' => $version,
        'website' => $website,
        'sanatizedWebsite' => $sanatizedWebsite,
        'lastUpdated' => $lastUpdated,
        'activeInstallations' => $activeInstallations,
        'reqWpVersion' => $reqWpVersion,
        'testedWpVersion' => null,
        'reqPhpVersion' => $reqPhpVersion,
        'description' => $description,
        'link' => $directoryUrl,
    ];

    return $theme;
}

// Returns the theme information in the website given a theme path
function find_theme_info_in_website($themeSlug, $themePath)
{
    require_once 'get_content.php';
    $styleCssUrl =  $themePath . '/style.css';
    $styleCssContent = get_content($styleCssUrl);

    preg_match('/Theme Name: (.*)/', $styleCssContent, $matches);
    if (!isset($matches[1])) {
        // Convert "plugin-slug" to "Plugin Slug"
        $words = explode('-', $themeSlug);
        $words = array_map('ucfirst', $words);
        $themeTitle = implode(' ', $words);
    }
    $title = $matches[1] ?? $themeTitle;

    preg_match('/Theme URI: (.*)/', $styleCssContent, $matches);
    $website = $matches[1] ?? null;

    $sanatizedWebsite = str_replace(['http://', 'https://'], '', $website);

    preg_match('/Author: (.*)/', $styleCssContent, $matches);
    $author = $matches[1] ?? "No author found";

    preg_match('/Version: (.*)/', $styleCssContent, $matches);
    $version = $matches[1] ?? null;

    preg_match('/Requires at least: (.*)/', $styleCssContent, $matches);
    $reqWpVersion = isset($matches[1]) ? $matches[1] . ' or higher' : null;

    preg_match('/Tested up to: (.*)/', $styleCssContent, $matches);
    $testedWpVersion = $matches[1] ?? null;

    preg_match('/Requires PHP: (.*)/', $styleCssContent, $matches);
    $reqPhpVersion = isset($matches[1]) ? $matches[1] . ' or higher' : null;

    preg_match('/Description: (.*)Version:/', $styleCssContent, $matches);
    $description = trim($matches[1] ?? "No description provided");

    $screenshot = get_theme_screenshot_in_website($themePath);

    $theme = [
        'screenshot' => $screenshot,
        'title' => $title,
        'author' => $author,
        'version' => $version,
        'website' => $website,
        'sanatizedWebsite' => $sanatizedWebsite,
        'lastUpdated' => null,
        'activeInstallations' => null,
        'reqWpVersion' => $reqWpVersion,
        'testedWpVersion' => $testedWpVersion,
        'reqPhpVersion' => $reqPhpVersion,
        'description' => $description,
        'link' => null,
    ];

    return $theme;
}

// Returns the screenshot URL of the theme
function get_theme_screenshot_in_website($themePath)
{
    $screenshotUrls = [
        $themePath . '/screenshot.png',
        $themePath . '/screenshot.jpg'
    ];

    foreach ($screenshotUrls as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode == 200) {
            return $url;
        }
    }

    return '/no-theme-screenshot.svg';
}
