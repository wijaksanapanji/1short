<?php

declare(strict_types=1);

define('DB_HOST', 'mysql');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_DATABASE', '1short');

class App
{
    protected PDO $database;

    public function __construct()
    {
        $this->initDatabase();
    }
    
    protected function initDatabase(): void
    {
        $this->database = new PDO('mysql:host='.DB_HOST.';dbname='.DB_DATABASE, DB_USER, DB_PASSWORD);
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function view(string $body): void
    {
        $html = <<<EOD
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>1Short - 1 File URL Shortener</title>
            <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
        </head>
        <body>
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 my-6">
                <h1 class="text-xl">1Short - One PHP File URL Shortener</h1>
                <div class="my-4">
                    $body
                </div>
            </div>
        </body>
        </html>
        EOD;

        echo $html;
    }

    protected function handleIndex()
    {
        if(strtoupper($_SERVER['REQUEST_METHOD']) == 'POST') {
            $this->shortenUrl($_REQUEST['url']);
        } else {
            $form = <<<EOD
            <form action="/" method="POST">
                <div>
                <label for="url" class="block text-sm font-medium leading-6 text-gray-900">URL</label>
                    <div class="mt-2">
                        <input type="url" name="url" id="url" class="block w-1/2 rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="https://example.com">
                    </div>
                </div>
                <button type="submit" class="mt-4 rounded bg-indigo-600 px-2 py-1 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Submit</button>
            </form>    
            EOD;
            
            $this->view($form);
        }
        die;
    }

    protected function shortenUrl(string $url)
    {
        $id = uniqid();

        $statement = $this->database->prepare('INSERT INTO urls (shorten_url, original_url) VALUES (:shorten_url, :original_url)');
        $statement->bindParam(':shorten_url', $id);
        $statement->bindParam(':original_url', $url);
        $statement->execute();

        $shortenUrl = (empty($_SERVER['HTTPS']) ? 'http' : 'https').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'s/'.$id;

        $this->view(<<<EOD
            <div class="flex flex-col">
                <p class="text-sm">Below is your shorten URL</p>
                <a href="$shortenUrl" target="_blank" class="underline text-indigo-500">$shortenUrl</p>
            </div>
        EOD);

        die;
    }
    
    protected function handleRedirect($uri)
    {
        $id = str_replace('/s/', '', $uri);

        $statement = $this->database->prepare('SELECT * FROM urls WHERE shorten_url = :shorten_url LIMIT 1');
        $statement->bindParam(':shorten_url', $id);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        $url = $statement->fetch();

        if(! $url) {
            $this->handleNotFound();
        }

        header('Location: '.$url['original_url']);
        die;
    }

    protected function handleNotFound(): void
    {
        http_response_code(404);
        echo 'Page not found.';
        die;
    }

    public function serve(): void
    {
        $uri = $_SERVER['REQUEST_URI'];

        if($uri == '/') {
            $this->handleIndex();
        } else if(preg_match('/\/s\/.*/', $uri)) {
            $this->handleRedirect($uri);
        } else {
            $this->handleNotFound();
        }
    }
}

$app = new App();
$app->serve();
