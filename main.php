<?php

require_once 'vendor/autoload.php';

use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\DomCrawler\Crawler;

$failedLimit = 50;
$connectionTries = 5;
$booksInFileLimit = 100;
for($i=0; $i<50; $i++){
    testLink($i);
    getCrawler($i);
}

exit();

if(!file_exists('files/status.txt')){
    setStatusNumber();
    echo "file status.txt created \n";
}else{
    echo "starting from last update \n";
}
$flag = true;
$startingNumber = getStatusNumber();
while(true){
    $flag = false;
    $fileData = [];
    $num = getStatusNumber();
    if(($startingNumber + 400) < $num)break;
    $fileName = createFileNamePrefix($num);
    echo "getting books from " . $num . " to " . ($num + 100) . "\n";
    for($i=0; $i<100; $i++){
        if(!testLink($num)){
            $num++;
            continue;
        }else{
            $crawler = getCrawler($num);
            if(!$crawler){
                $num++;
                continue;
            }
            $flag = true;
            $data = getBookInfo($crawler);
            $imageLink = getImageLink($crawler);
            $downloadLink = getDownloadLink($crawler);
            $myData = saveBook($data,$imageLink,$downloadLink);
            $fileData[] = $myData;
            echo $myData->book_name . "\n";
            $num++;
        }
    }
    if(!$flag)break;
    $fileData = formatFileData($fileData);
    saveFile($fileName,$fileData);
    setStatusNumber($num);
    echo "file " . $fileName . " created \n";
}
$num = getStatusNumber();
if(!$flag)echo "no more new books found \n";
echo "downloaded books data from " . $startingNumber . " to " . $num;

function testing($num){
    $link = 'https://smtebooks.com/file/' . $num;
    $client = new GuzzleClient();
    $response = $client->get($link);
    return $response->getStatusCode() == 200;
}

function testLink($num){
    try{
        echo $num . " \n";
        return testing($num);
    }catch (Exception $e){
        echo $e . " \n";
        return false;
    }
}

function handleCrawler($num){
    $link = 'https://smtebooks.com/file/' . $num;
    $client = new Client();
    return $client->request('GET',$link);
}

function getCrawler($num){
    try{
        echo $num . " \n";
        return handleCrawler($num);
    }catch (Exception $e){
        echo $e . "\n";
        return false;
    }
}

function getBookInfo($crawler){
    $bookInfo = $crawler->filter('td > strong');
    $data = [];
    foreach ($bookInfo as $domElement) {
        $data[] = $domElement->nodeValue;
    }
    return $data;
}

function getImageLink($crawler){
    $imageLink = $crawler->filter('div')
        ->reduce(function (Crawler $node,$i){
            return $node->attr('itemprop') == 'image';
        });
    $imageLink = $imageLink->first()->attr('data-img');
    return $imageLink;
}

function getDownloadLink($crawler){
    $downloadLink = $crawler->filter('a')
        ->reduce(function (Crawler $node,$i){
            return $node->attr('class') == 'Download btn btn-block btn-lg btn-success';
        });
    $downloadLink = $downloadLink->first()->attr('href');
    return $downloadLink;
}

function saveBook($data,$imageLink,$downloadLink){
    $myData = new stdClass();
    $myData->book_name = trim($data[1]);
    $myData->edition = trim($data[3]);
    $myData->authors = explode(',',trim($data[5])) ;
    $myData->ISPN = trim($data[7]);
    $myData->pages_count = (int)(explode(' ',trim($data[9]))[0]);
    $myData->imageLink = $imageLink;
    $myData->downloadLink = $downloadLink;
    return $myData;
}

function formatFileData($fileData){
    return json_encode($fileData,JSON_PRETTY_PRINT);
}

function getStatusNumber(){
    $statusFile = fopen('files/status.txt','r');
    $statusNumber = fgets($statusFile);
    fclose($statusFile);
    return $statusNumber;
}

function setStatusNumber($num = 0){
    $statusFile = fopen('files/status.txt','w');
    fwrite($statusFile,$num);
    fclose($statusFile);
    return $num;
}

function createFileNamePrefix($num = '0'){
    if(strlen($num) == 5)return $num;
    if($num == 0)return '00000';
    for($i=0; $i<=(5-strlen($num)); $i++){
        $num = 0 . $num;
    }
    return $num;
}

function saveFile($fileName,$fileData){
    $path = 'files/' . $fileName . '.json';
    $myFile = fopen($path,'w');
    fwrite($myFile,$fileData);
    fclose($myFile);
    return true;
}