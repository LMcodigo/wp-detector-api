<?php
<<<<<<< HEAD
require 'database_read.php';
require 'parse_wordpress.php';
require 'find_version.php';
require 'database_connection.php';
require 'database_write.php';

function find_plugins($html, $wpContent,$url) {
    // Returns a list of all the plugins detected in the html content

    // $plugin1 = [
    //     'banner' => 'https://ps.w.org/wordpress-seo/assets/banner-772x250.png',
    //     'icon' => 'https://ps.w.org/wordpress-seo/assets/icon.svg',
    //     'title' => 'Yoast SEO',
    //     'author' => 'Team Yoast',
    //     'version' => '3.4.0',
    //     'website' => 'https://yoast.com',
    //     'sanatizedWebsite' => 'yoast.com',
    //     'reqWpVersion' => '6.3',
    //     'testedWpVersion' => '6.4.3',
    //     'reqPhpVersion' => '7.2.5',
    //     'description' => 'Supercharge your website’s visibility and attract organic traffic with Yoast SEO, the WordPress SEO plugin trusted by millions worldwide. With those millions of users, we’ve definitely helped someone like you! Users of our plugin range from owners of small-town bakeries and local physical stores to some of the world’s largest and most influential organizations. And we’ve done this since 2008!',
    //     'link' => 'https://yoast.com/?utm_source=wp-detector',
    // ];

    // return [$plugin1, $plugin1];

    if($url===null) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $plugins = [];

        $patterns = [
            'script' => '/<script[^>]*src=[\'"]([^\'"]+)[\'"][^>]*>/i',
            'link' => '/<link[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i',
            'img' => '/<img[^>]*src=[\'"]([^\'"]+)[\'"][^>]*>/i',
        ];

        $allMatches = [];
        
        foreach ($patterns as $element => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $allMatches[$element] = $matches[1];
            }
        }

        foreach ($allMatches as $element => $matches) {
            foreach ($matches as $link) {
                $plugins = process_plugin($link, $wpContent, $plugins);
            }
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
            $slugs = $row['plugins'];
        }
        $slugs = str_replace(' ', '', $slugs);
        $pluginSlugs = explode(',', $slugs);
        $plugins = plugins_from_database($pluginSlugs);
    }

    return $plugins;

}

function plugins_from_database($pluginSlugs){
    foreach ($pluginSlugs as $pluginSlug) {
        $retrievedData = getDataBySlug('plugins', $pluginSlug);
        $description = $retrievedData['pluginDescription'];
        if (strlen($description) > 300) {
            $description = substr($description, 0, 300) . '...';
        }
        $newPlugin = [
            'icon' => $retrievedData['icon'],
            'banner' => $retrievedData['banner'],
            'title' => $retrievedData['title'],
            'author' => $retrievedData['author'],
            'version' => $retrievedData['version'],
            'website' => $retrievedData['website'],
            'sanatizedWebsite' => $retrievedData['sanatizedWebsite'],
            'reqWpVersion' => $retrievedData['reqWpVersion'],
            'testedWpVersion' => $retrievedData['testedWpVersion'],
            'reqPhpVersion' => $retrievedData['reqPhpVersion'],
            'description' => $description,
            'link' => $retrievedData['link'],
        ];
        $plugins[] = $newPlugin;
        $result = updateTimesAnalyzed('plugins',$pluginSlug);
    }
}

function process_plugin($url, $wpContentPath, $plugins) {

    if (strpos($url, 'plugins/') !== false) {
        $pattern = '/plugins\/(.*?)\//';

        if (preg_match($pattern, $url, $matches)) {
            $pluginSlug = $matches[1];

            if (!in_array($pluginSlug, $plugins) && $pluginSlug !== 'src' && $pluginSlug !== 'search') {
                $pluginPath = $wpContentPath . 'plugins/' . $pluginSlug . '/readme.txt';
                
                if (slugExists('plugins', $pluginSlug)) {
                    
                    $retrievedData = getDataBySlug('plugins', $pluginSlug);
                    if ($retrievedData !== null) {
                        $description = $retrievedData['pluginDescription'];
                        if (strlen($description) > 300) {
                            $description = substr($description, 0, 300) . '...';
                        }
                        $newPlugin = [
                            'icon' => $retrievedData['icon'],
                            'banner' => $retrievedData['banner'],
                            'title' => $retrievedData['title'],
                            'author' => $retrievedData['author'],
                            //'version' => find_version($pluginPath, 'plugin'),
                            'version' => $retrievedData['version'],
                            'website' => $retrievedData['website'],
                            'sanatizedWebsite' => $retrievedData['sanatizedWebsite'],
                            'reqWpVersion' => $retrievedData['reqWpVersion'],
                            'testedWpVersion' => $retrievedData['testedWpVersion'],
                            'reqPhpVersion' => $retrievedData['reqPhpVersion'],
                            'description' => $description,
                            'link' => $retrievedData['link'],
                        ];
                        $plugins[] = $newPlugin;
                        $result = updateTimesAnalyzed('plugins',$pluginSlug);
                    }
                } else {
                    $pluginDetails = parse_plugin_info($pluginPath);

                    $wp_html = @file_get_contents('https://wordpress.org/plugins/' . $pluginSlug . '/');
                    $iconUrl = parse_wordpress($wp_html, $pluginSlug, 'icon');
                    $bannerUrl = parse_wordpress($wp_html, $pluginSlug, 'banner');

                    $link = parse_wordpress($wp_html, $pluginSlug, 'url');
                    $parsed_url = parse_url($link);
                    $sanatizedWebsite = isset($parsed_url['host']) ? $parsed_url['host'] : '';
                    $website = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
                    $website .= isset($parsed_url['host']) ? $parsed_url['host'] : '';

                    $newPlugin = [
                        'icon' => $iconUrl,
                        'banner' => $bannerUrl,
                        'title' => $pluginDetails['title'] ?? null,
                        'author' => $pluginDetails['author'] ?? null,
                        'version' => $pluginDetails['version'] ?? null,
                        'website' => $website,
                        'sanatizedWebsite' => $sanatizedWebsite,
                        'reqWpVersion' => $pluginDetails['reqWpVersion'] ?? null,
                        'testedWpVersion' => $pluginDetails['testedWpVersion'] ?? null,
                        'reqPhpVersion' => $pluginDetails['reqPhpVersion'] ?? null,
                        'description' => $pluginDetails['description'] ?? null,
                        'link' => $link,
                    ];
                    $plugins[] = $newPlugin;

                    $newPlugin['slug'] = $pluginSlug;
                    $newPlugin['times_analyzed'] = 1;
                    $result = setDataBySlug('plugins', $newPlugin);
                }
            }
        }
    }
}

function parse_plugin_info($pluginPath) {
    $pluginContent = @file_get_contents($pluginPath);
    $pluginDetails = [];
                
    if($pluginContent !== false && !empty($pluginContent)) {
        preg_match('/Contributors:\s*(.*?)(?:\n|$)/i', $pluginContent, $matches);
        if (isset($matches[1])) {
            $pluginDetails['author'] = trim($matches[1]);
        } else { $pluginDetails['author'] = null; }
        
        preg_match('/== Description ==\s+(.*?)\s+(^=|\z)/s', $pluginContent, $matches);
        if (isset($matches[1])) {
            $description = trim($matches[1]);
			if (strlen($description) > 300) {
				$pluginDetails['description'] = substr($description, 0, 300) . '...';
			} else {
				$pluginDetails['description'] = $description;
			}    
        } else { $pluginDetails['description'] = null; }
        
        $firstLine = strtok($pluginContent, "\n");
        preg_match('/^===\s*(.*?)\s*===/', $firstLine, $matches);
		if (isset($matches[1])) {
			$pluginDetails['title'] = trim($matches[1]);
		} else {
			$pluginDetails['title'] = null;
		}
		
		preg_match('/== Changelog ==\s+=\s*([\d.]+)\s*=/i', $pluginContent, $matches);
		if (isset($matches[1])) {
			$pluginDetails['version'] = trim($matches[1]);
		} else {
			$pluginDetails['version'] = null;
		}
		
		preg_match('/Requires at least:\s*(.*?)(?:\n|$)/i', $pluginContent, $matches);
		if (isset($matches[1])) {
			$pluginDetails['reqWpVersion'] = trim($matches[1]);
		} else {
			$pluginDetails['reqWpVersion'] = null;
		}

		preg_match('/Tested up to:\s*(.*?)(?:\n|$)/i', $pluginContent, $matches);
		if (isset($matches[1])) {
			$pluginDetails['testedWpVersion'] = trim($matches[1]);
		} else {
			$pluginDetails['testedWpVersion'] = null;
		}

		preg_match('/Requires PHP:\s*(.*?)(?:\n|$)/i', $pluginContent, $matches);
		if (isset($matches[1])) {
			$pluginDetails['reqPhpVersion'] = trim($matches[1]);
		} else {
			$pluginDetails['reqPhpVersion'] = null;
		}

    } 
    return $pluginDetails;
}


=======

require_once 'database_connection.php';

// Returns all the plugins of the given url
function find_plugins($links)
{
    $plugins = [];

    $db = new Database();
    $db->connect();

    foreach ($links as $link) {
        if (preg_match('/.*\/plugins\/([^\/]*)/', $link, $matches)) {
            $pluginSlug = $matches[1];

            // Parse the URL to get the scheme and host
            $parsedUrl = parse_url($link);
            $rootDomain = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            $pluginPath = $rootDomain . '/wp-content/plugins/' . $pluginSlug;

            if (!array_key_exists($pluginSlug, $plugins) && preg_match('/^[a-z\-]+$/', $pluginSlug)) {
                $pluginInfo = get_plugin_info($db, $pluginSlug, $pluginPath);
                if (!empty($pluginInfo)) {
                    $plugins[$pluginSlug] = $pluginInfo;
                }
            }
        }
    }

    $db->close();

    return $plugins;
}

// Returns the plugin information of a given plugin slug
function get_plugin_info($db, $pluginSlug, $pluginPath)
{
    $result = $db->query("SELECT * FROM plugins WHERE slug = '$pluginSlug'");
    $row = $result->fetch_assoc();

    if (empty($row)) {
        //$pluginInfo = find_plugin_info_in_directory($pluginSlug);
        //if (empty($pluginInfo)) {
        $pluginInfo = find_plugin_info_in_website($pluginSlug, $pluginPath);
        //}
        //if (empty($pluginInfo)) {
        //    return null;
        //}


        $banner = $pluginInfo['banner'];
        $icon = $pluginInfo['icon'];
        $title = $pluginInfo['title'];
        $contributors = $pluginInfo['contributors'];
        $version = $pluginInfo['version'];
        $website = $pluginInfo['website'];
        $sanatizedWebsite = $pluginInfo['sanatizedWebsite'];
        $lastUpdated = $pluginInfo['lastUpdated'];
        $activeInstallations = $pluginInfo['activeInstallations'];
        $reqWpVersion = $pluginInfo['reqWpVersion'];
        $testedWpVersion = $pluginInfo['testedWpVersion'];
        $reqPhpVersion = $pluginInfo['reqPhpVersion'];
        $description = $pluginInfo['description'];
        $link = '';

        // Insert the plugin info into the database
        $db->query("INSERT INTO plugins (slug, banner, icon, title, contributors, version, website, sanatizedWebsite, lastUpdated, activeInstallations, reqWpVersion, testedWpVersion, reqPhpVersion, description, link, timesAnalyzed, lastAnalyzed) VALUES ('$pluginSlug', '$banner', '$icon', '$title', '$contributors', '$version', '$website', '$sanatizedWebsite', '$lastUpdated', '$activeInstallations', '$reqWpVersion', '$testedWpVersion', '$reqPhpVersion', '$description', '$link',  1, NOW())");
    
    } else {
        $pluginInfo = [
            'banner' => $row['banner'],
            'icon' => $row['icon'],
            'title' => $row['title'],
            'contributors' => $row['contributors'],
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
        $db->query("UPDATE plugins SET timesAnalyzed = timesAnalyzed + 1, lastAnalyzed = NOW() WHERE slug = '$pluginSlug'");
    }

    return $pluginInfo;
}

// Returns the plugin information in the wordpress directory given a plugin slug
function find_plugin_info_in_directory($pluginsSlug)
{
    require_once 'get_content.php';
    $directoryUrl = 'https://wordpress.org/plugins/' . $pluginsSlug;
    $directoryContent = get_content($directoryUrl);

    if (empty($directoryContent)) {
        return null;
    }

    preg_match('/<h1 class="plugin-title">(.*?)<\/h1>/', $directoryContent, $matches);
    $title = $matches[1] ?? null;

    preg_match('/<span class="byline">By <span class="author vcard"><a class="url fn n" rel="nofollow" href=".*?">(.*?)<\/a><\/span><\/span>/', $readmeTxtContent, $matches);
    $author = $matches[1] ?? "No contributors found";

    preg_match('/<li>\s*Version: <strong>(.*?)<\/strong>\s*<\/li>/', $readmeTxtContent, $matches);
    $version = $matches[1] ?? null;

    preg_match('/<li>\s*Last updated: <strong><span>(.*?)<\/span><\/strong>\s*<\/li>/', $directoryContent, $matches);
    $lastUpdated = $matches[1] ?? null;

    preg_match('/<li>\s*Active installations: <strong>(.*?)<\/strong>\s*<\/li>/', $directoryContent, $matches);
    $activeInstallations = $matches[1] ?? null;

    preg_match('/<a href="(.*?)" rel="nofollow">Support<\/a>/', $readmeTxtContent, $matches);
    $website = $matches[1] ?? null;

    $sanatizedWebsite = str_replace(['http://', 'https://'], '', $website);

    preg_match('/<li>\s*WordPress Version:\s*<strong>\s*(.*?) or higher\s*<\/strong>\s*<\/li>/', $readmeTxtContent, $matches);
    $reqWpVersion = isset($matches[1]) ? $matches[1] . ' or higher' : null;

    preg_match('/<li>\s*Tested up to: <strong>(.*?)<\/strong>\s*<\/li>/', $readmeTxtContent, $matches);
    $testedWpVersion = $matches[1] ?? null;

    preg_match('/<li>\s*PHP Version:\s*<strong>\s*(.*?) or higher\s*<\/strong>\s*<\/li>/', $readmeTxtContent, $matches);
    $reqPhpVersion = isset($matches[1]) ? $matches[1] . ' or higher' : null;

    preg_match('/== Description ==\n\n(.*)/', $readmeTxtContent, $matches);
    $description = $matches[1] ?? 'No description provided';

    preg_match('/<img class="plugin-icon" src="(.*?)">/', $directoryContent, $matches);
    $icon = $matches[1] ?? '/no-plugin-icon.svg';

    preg_match("/background-image: url\('(.*?)'\);/", $directoryContent, $matches);
    $banner = $matches[1] ?? '/no-plugin-banner.svg';

    $plugin = [
        'banner' => $banner,
        'icon' => $icon,
        'title' => $title,
        'contributors' => $author,
        'version' => $version,
        'website' => $website,
        'sanatizedWebsite' => $sanatizedWebsite,
        'lastUpdated' => $lastUpdated,
        'activeInstallations' => $activeInstallations,
        'reqWpVersion' => $reqWpVersion,
        'testedWpVersion' => $testedWpVersion,
        'reqPhpVersion' => $reqPhpVersion,
        'description' => $description,
        'link' => $directoryUrl,
    ];

    return $plugin;
}

// Returns the plugin information given a plugin path
function find_plugin_info_in_website($pluginSlug, $pluginPath)
{
    require_once 'get_content.php';
    $readmeTxtUrl =  $pluginPath . '/readme.txt';
    $readmeTxtContent = get_content($readmeTxtUrl);

    preg_match('/=== (.*) ===/', $readmeTxtContent, $matches);
    if (!isset($matches[1])) {
        // Convert "plugin-slug" to "Plugin Slug"
        $words = explode('-', $pluginSlug);
        $words = array_map('ucfirst', $words);
        $pluginTitle = implode(' ', $words);
    }
    $title = $matches[1] ?? $pluginTitle;

    preg_match('/Contributors: (.*)/', $readmeTxtContent, $matches);
    $author = $matches[1] ?? "No contributors found";

    preg_match('/Stable tag: (.*)/', $readmeTxtContent, $matches);
    $version = $matches[1] ?? null;

    preg_match('/Donate link: (.*)/', $readmeTxtContent, $matches);
    $website = $matches[1] ?? null;

    $sanatizedWebsite = str_replace(['http://', 'https://'], '', $website);

    preg_match('/Requires at least: (.*)/', $readmeTxtContent, $matches);
    $reqWpVersion = isset($matches[1]) ? $matches[1] . ' or higher' : null;

    preg_match('/Tested up to: (.*)/', $readmeTxtContent, $matches);
    $testedWpVersion = $matches[1] ?? null;

    preg_match('/Requires PHP: (.*)/', $readmeTxtContent, $matches);
    $reqPhpVersion = isset($matches[1]) ? $matches[1] . ' or higher' : null;

    preg_match('/== Description ==\n\n(.*)/', $readmeTxtContent, $matches);
    $description = $matches[1] ?? 'No description provided';

    $banner = '/no-plugin-banner.svg';
    $icon = '/no-plugin-icon.svg';

    $plugin = [
        'banner' => $banner,
        'icon' => $icon,
        'title' => $title,
        'contributors' => $author,
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

    return $plugin;
}
>>>>>>> upstream/main
?>