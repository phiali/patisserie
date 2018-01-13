<?php

namespace Patisserie\Controllers;

use Patisserie\Auth;
use Patisserie\Patisserie;
use PhpXmlRpc\PhpXmlRpc;
use PhpXmlRpc\Value;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

class XmlRpcController
{
    /** @var ContainerInterface */
    protected $container;

    /** @var  Twig */
    protected $view;

    /** @var  \Slim\Router */
    protected $router;

    /** @var string */
    protected $homePageUrl;

    /** @var array */
    protected $siteConfig;

    public function __construct(ContainerInterface $container)
    {
        $this->container  = $container;
        $this->view       = $container->get('view');
        $this->router     = $container->get('router');
        $this->siteConfig = $container->get('siteConfig');
    }

    public function index(Request $request, Response $response, array $args)
    {
        $homePage = sprintf(
            "%s://%s",
            (filter_input(INPUT_SERVER, 'HTTPS')) ? 'https' : 'http',
            filter_input(INPUT_SERVER, 'HTTP_HOST')
        );
        $apiLink = sprintf(
            "%s%s",
            $homePage,
            str_replace(
                '?' . filter_input(INPUT_SERVER, 'QUERY_STRING'),
                '',
                filter_input(INPUT_SERVER, 'REQUEST_URI')
            )
        );

        $this->homePageUrl = $homePage;

        if (!is_null($request->getQueryParam('rsd'))) {
            $response = $response->withHeader('Content-type', 'text/xml');
            return $this->view->render($response, 'xmlrpc/rsd.twig', [
                'homePageLink' => $homePage,
                'apiLink'      => $apiLink
            ]);
        }

        $server = new \PhpXmlRpc\Server(null, false);
        $server->compress_response = true;

        $server->add_to_map('metaWeblog.getUsersBlogs', function(\PhpXmlRpc\Request $message) {
            return self::getUsersBlogs(
                $message->getParam(1)->scalarval(),
                $message->getParam(2)->scalarval(),
                $this->siteConfig
            );
        });

        $server->add_to_map('metaWeblog.getUserInfo', function(\PhpXmlRpc\Request $message) {
            return self::getUserInfo(
                $message->getParam(1)->scalarval(),
                $message->getParam(2)->scalarval(),
                $this->siteConfig
            );
        });

        $server->add_to_map('blogger.getUserInfo', function(\PhpXmlRpc\Request $message) {
            return self::getUserInfo(
                $message->getParam(1)->scalarval(),
                $message->getParam(2)->scalarval(),
                $this->siteConfig
            );
        });

        $server->add_to_map('metaWeblog.getCategories', function(\PhpXmlRpc\Request $message) {
            return self::getCategories(
                $message->getParam(1)->scalarval(),
                $message->getParam(2)->scalarval()
            );
        });

        $server->add_to_map('metaWeblog.getRecentPosts', function(\PhpXmlRpc\Request $message) {
            return self::getRecentPosts(
                $message->getParam(1)->scalarval(),
                $message->getParam(2)->scalarval(),
                $message->getParam(3)->scalarval()
            );
        });

        $server->add_to_map('metaWeblog.newPost', function(\PhpXmlRpc\Request $message) {
            return self::newPost(
                $message->getParam(1)->scalarval(),
                $message->getParam(2)->scalarval(),
                $message->getParam(3)->scalarval(),
                $message->getParam(4)->scalarval()
            );
        });

        $server->add_to_map('blogger.newPost', function(\PhpXmlRpc\Request $message) {
            return self::newPost(
                $message->getParam(2)->scalarval(),
                $message->getParam(3)->scalarval(),
                ['description' => $message->getParam(4)],
                $message->getParam(5)->scalarval()
            );
        });

        $server->add_to_map('metaWeblog.editPost', function(\PhpXmlRpc\Request $message) {
            return self::editPost(
                $message->getParam(0)->scalarval(),
                $message->getParam(1)->scalarval(),
                $message->getParam(2)->scalarval(),
                $message->getParam(3)->scalarval(),
                $message->getParam(4)->scalarval()
            );
        });

        $server->add_to_map('metaWeblog.getPost', function(\PhpXmlRpc\Request $message) {
            return self::getPost(
                $message->getParam(0)->scalarval(),
                $message->getParam(1)->scalarval(),
                $message->getParam(2)->scalarval()
            );
        });

        $server->add_to_map('metaWeblog.newMediaObject', function(\PhpXmlRpc\Request $message) {
            return self::newMediaObject(
                $message->getParam(1)->scalarval(),
                $message->getParam(2)->scalarval(),
                $message->getParam(3)->scalarval()
            );
        });

        $response = $response->withHeader('Content-type', 'text/xml');
        $body = $response->getBody();

        try {
            $body->write($server->service(null, true));
        } catch (\Exception $exception) {
        }

        return $response;
    }

    /**
     * Retrieve a listing of the blogs available for the user
     * @param string $username
     * @param string $password
     * @param array $siteConfiguration
     * @return \PhpXmlRpc\Response
     */
    public static function getUsersBlogs($username, $password, $siteConfiguration)
    {
        $auth = new Auth();
        if (!$auth->attempt($username, $password)) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, 'Invalid username or password');
        }

        $struct = [
            'blogid'   => new Value('1', Value::$xmlrpcString),
            'url'      => new Value($siteConfiguration['baseUrl'], Value::$xmlrpcString),
            'blogName' => new Value($siteConfiguration['siteTitle'], Value::$xmlrpcString),
        ];

        return new \PhpXmlRpc\Response(new Value([new Value($struct, Value::$xmlrpcStruct)], Value::$xmlrpcArray));
    }

    /**
     * Retrieve the information for the user
     * @param string $username
     * @param string $password
     * @param array $siteConfiguration
     * @return \PhpXmlRpc\Response
     */
    public static function getUserInfo($username, $password, $siteConfiguration)
    {
        $auth = new Auth();
        if (!$auth->attempt($username, $password)) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, 'Invalid username or password');
        }

        $struct = [
            'nickname'  => new Value($siteConfiguration['author']['nickname'], Value::$xmlrpcString),
            'userid'    => new Value('1', Value::$xmlrpcString),
            'url'       => new Value($siteConfiguration['baseUrl'], Value::$xmlrpcString),
            'email'     => new Value($siteConfiguration['author']['email'], Value::$xmlrpcString),
            'lastname'  => new Value($siteConfiguration['author']['lastName'], Value::$xmlrpcString),
            'firstname' => new Value($siteConfiguration['author']['firstName'], Value::$xmlrpcString),
        ];

        return new \PhpXmlRpc\Response(new Value($struct, Value::$xmlrpcStruct));
    }

    /**
     * Retrieve the available categories
     * @param string $username
     * @param string $password
     * @return \PhpXmlRpc\Response
     */
    public static function getCategories($username, $password)
    {
        $auth = new Auth();
        if (!$auth->attempt($username, $password)) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, 'Invalid username or password');
        }

        return new \PhpXmlRpc\Response(new Value([], Value::$xmlrpcArray));
    }

    /**
     * Retrieve the available posts
     * @param string $username
     * @param string $password
     * @param int $numberOfPosts
     * @return \PhpXmlRpc\Response
     */
    public static function getRecentPosts($username, $password, $numberOfPosts)
    {
        $auth = new Auth();
        if (!$auth->attempt($username, $password)) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, 'Invalid username or password');
        }

        try {
            $posts      = [];
            $patisserie = new Patisserie([]);
            $entries    = $patisserie->getEntries(
                'entryCreationTimestamp', SORT_DESC, $numberOfPosts, true
            );

            foreach ($entries as $entry) {
                $entryTitle = null;
                if ($entry->hasFrontMatter('title')) {
                    $entryTitle = $entry->getFrontMatter('title');
                }

                $entryDate = null;
                if ($entry->hasFrontMatter('created_at')) {
                    $entryDate = $entry->getFormattedDate('created_at', 'c');
                }

                $entryContent = file_get_contents($entry->getFilename());

                $posts[] = new Value([
                    'postid'      => new Value($entry->getRelativeUrl(), Value::$xmlrpcString),
                    'title'       => new Value($entryTitle, Value::$xmlrpcString),
                    'description' => new Value($entryContent, Value::$xmlrpcString),
                    'dateCreated' => new Value($entryDate, Value::$xmlrpcString)
                ], Value::$xmlrpcStruct);
            }

            return new \PhpXmlRpc\Response(new Value($posts, Value::$xmlrpcArray));
        } catch (\Exception $exception) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, $exception->getMessage());
        }
    }

    /**
     * Retrieve a specific post
     * @param string $postID
     * @param string $username
     * @param string $password
     * @return \PhpXmlRpc\Response
     */
    public static function getPost($postID, $username, $password)
    {
        $auth = new Auth();
        if (!$auth->attempt($username, $password)) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, 'Invalid username or password');
        }

        try {
            $relativeFile = $postID . DIRECTORY_SEPARATOR . 'index.md';
            if ('/' === substr($postID, -1)) {
                $relativeFile = $postID . 'index.md';
            }

            $patisserie = new Patisserie([]);
            $entry      = $patisserie->getEntry($relativeFile, true);

            $entryTitle = null;
            if ($entry->hasFrontMatter('title')) {
                $entryTitle = $entry->getFrontMatter('title');
            }

            $entryDate = null;
            if ($entry->hasFrontMatter('created_at')) {
                $entryDate = $entry->getFormattedDate('created_at', 'c');
            }

            $entryContent = file_get_contents($entry->getFilename());

            $post       = [
                'postid'      => new Value($entry->getRelativeUrl(), Value::$xmlrpcString),
                'title'       => new Value($entryTitle, Value::$xmlrpcString),
                'description' => new Value($entryContent, Value::$xmlrpcString),
                'dateCreated' => new Value($entryDate, Value::$xmlrpcString)
            ];

            return new \PhpXmlRpc\Response(new Value($post, Value::$xmlrpcStruct));
        } catch (\Exception $exception) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, $exception->getMessage());
        }
    }

    /**
     * Create a new post
     * See https://codex.wordpress.org/XML-RPC_MetaWeblog_API#metaWeblog.newPost
     * @param string $username
     * @param string $password
     * @param array $content
     * @param bool $publish
     * @return \PhpXmlRpc\Response
     */
    public static function newPost($username, $password, array $content, $publish)
    {
        /**
         * Logic:
         *  If title is empty then it's an 'aside' so generate time-based URL and include aside.twig
         *  If we have a title then slugify that and have URL as year/month/day/slug
         */
        $auth = new Auth();
        if (!$auth->attempt($username, $password)) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, 'Invalid username or password');
        }

        try {
            if (!array_key_exists('description', $content)) {
                throw new \RuntimeException('Entry content missing');
            }

            $now           = new \DateTime();
            $entryTitle    = $now->format('His');
            $entryTemplate = 'aside.md';
            $entryContent  = $content['description']->scalarVal();
            $entryBody     = null;

            if (array_key_exists('title', $content)) {
                $entryTitle    = Patisserie::sanitizeTitle($content['title']->scalarVal());
                $entryTemplate = 'post.md';
            }

            $relativePath =
                $now->format('/Y/m/d')
                . DIRECTORY_SEPARATOR
                . $entryTitle
                . DIRECTORY_SEPARATOR;

            $relativeFile =
                $relativePath
                . 'index.md';

            $entryPath =
                PUBLIC_FOLDER
                . $relativePath
                . 'index.md';

            if (file_exists(APPLICATION_PATH . "/templates/{$entryTemplate}")) {
                $entryBody = file_get_contents(APPLICATION_PATH . "/templates/{$entryTemplate}");
                $search = [
                    '{{title}}',
                    '{{timestamp}}'
                ];
                $replace = [
                    $entryTitle,
                    $now->format('Y-m-d H:i:s e')
                ];
                $entryBody = str_replace($search, $replace, $entryBody);
                $entryBody.= $entryContent;
            }

            // Set the publish flag
            if ($publish) {
                $frontMatter = new \FrontMatter($entryBody);
                $frontMatter->data['indexable'] = 'yes';
                $content = $frontMatter->data['content'];
                unset($frontMatter->data['content']);
                $frontMatter = $frontMatter->data;
                $entryBody = sprintf("---\n%s---\n%s", \Symfony\Component\Yaml\Yaml::dump($frontMatter), $content);
            }

            $directory = dirname($entryPath);

            if (!is_dir($directory)) {
                $existingUMask = umask(0);
                mkdir($directory, 0777, true);
                umask($existingUMask);
            }

            // Create the file
            if (!file_exists($entryPath)) {
                file_put_contents($entryPath, $entryBody);
                // Ensure that the file is writeable by all, see https://stackoverflow.com/a/1240731/89783
                chmod($entryPath, fileperms($entryPath) | 128 + 16 + 2);
            }

            $patisserie = new \Patisserie\Patisserie([]);
            $entry = $patisserie->getEntry($relativeFile);
            if ($entry->isIndexable()) {
                $patisserie->buildSite(false);
            } else {
                $patisserie->publishEntry($entry);
            }

            // Return the PostID
            return new \PhpXmlRpc\Response(new Value($relativePath, Value::$xmlrpcString));
        } catch (\Exception $exception) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, $exception->getMessage());
        }
    }

    /**
     * Edit an existing post
     * @param string $postID
     * @param string $username
     * @param string $password
     * @param array $content
     * @param $publish
     * @return \PhpXmlRpc\Response
     */
    public static function editPost($postID, $username, $password, array $content, $publish)
    {
        $auth = new Auth();
        if (!$auth->attempt($username, $password)) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, 'Invalid username or password');
        }

        try {
            $relativeFile = $postID . DIRECTORY_SEPARATOR . 'index.md';
            if ('/' === substr($postID, -1)) {
                $relativeFile = $postID . 'index.md';
            }
            $entryPath = PUBLIC_FOLDER . $relativeFile;

            /**
             * We'll trigger a site rebuild if either the new post is indexable or the previous one was. This needs to
             * be done so as to allow RSS feeds or the front page to be regenerated.
             */
            $patisserie    = new Patisserie([]);
            $rebuildSite   = false;
            $originalEntry = $patisserie->getEntry($relativeFile);
            file_put_contents($entryPath, $content['description']->scalarval(), LOCK_EX);
            $entry = $patisserie->getEntry($relativeFile);

            if ($entry->isIndexable() || $originalEntry->isIndexable()) {
                $rebuildSite = true;
            }

            if ($rebuildSite) {
                $patisserie->buildSite(false);
            } else {
                $patisserie->publishEntry($entry);
            }
        } catch (\Exception $exception) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, $exception->getMessage());
        }

        return new \PhpXmlRpc\Response(new Value(true, Value::$xmlrpcBoolean));
    }

    /**
     * Create a new media item (file upload)
     * @param string $username
     * @param string $password
     * @param array $data
     * @return \PhpXmlRpc\Response
     */
    public static function newMediaObject($username, $password, array $data)
    {
        $auth = new Auth();
        if (!$auth->attempt($username, $password)) {
            return new \PhpXmlRpc\Response(0, PhpXmlRpc::$xmlrpcerruser, 'Invalid username or password');
        }

        /**
         * Save to public/uploads
         *
         * Be careful with the filename seeing as it's user-supplied
         */
        $uploadPath = PUBLIC_FOLDER . DIRECTORY_SEPARATOR . 'uploads';
        $filePath   = $uploadPath . DIRECTORY_SEPARATOR . $data['name']->scalarval();
        $pathInfo   = pathinfo($filePath);
        $sanitisedFilePath = sprintf("%s%s%s", $pathInfo['dirname'], DIRECTORY_SEPARATOR, $pathInfo['basename']);

        $directory = dirname($sanitisedFilePath);

        if (!is_dir($directory)) {
            $existingUMask = umask(0);
            mkdir($directory, 0777, true);
            umask($existingUMask);
        }

        // Create the file ensuring that the file is writeable by all, see https://stackoverflow.com/a/1240731/89783
        $fileData = $data['bits']['base64'];
        file_put_contents($sanitisedFilePath, $fileData);
        chmod($sanitisedFilePath, fileperms($sanitisedFilePath) | 128 + 16 + 2);

        $returnData = [
            'id'   => new Value(sha1($pathInfo['basename']), Value::$xmlrpcString),
            'file' => new Value($pathInfo['basename'], Value::$xmlrpcString),
            'url'  => new Value(sprintf("/uploads/%s", $pathInfo['basename']), Value::$xmlrpcString),
            'type' => new Value(mime_content_type($sanitisedFilePath), Value::$xmlrpcString),
        ];

        return new \PhpXmlRpc\Response(new Value($returnData, Value::$xmlrpcStruct));
    }
}