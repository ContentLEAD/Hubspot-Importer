<?php
error_reporting(-1);

require_once 'SampleAPIClientLibrary/ApiHandler.php';
require_once 'hubspotClasses/class.leads.php';
require_once 'hubspotClasses/class.settings.php';
require_once 'hubspotClasses/class.events.php';
require_once 'hubspotClasses/class.leadnurturing.php';
require_once 'hubspotClasses/class.prospects.php';
require_once 'hubspotClasses/class.keywords.php';
require_once 'hubspotClasses/class.blog.php';
require_once 'RCClientLibrary/AdferoArticlesVideoExtensions/AdferoVideoClient.php';
require_once 'RCClientLibrary/AdferoArticles/AdferoClient.php';
require_once 'RCClientLibrary/AdferoPhotos/AdferoPhotoClient.php';

// Enter Brafton and Hubspot credentials
define("brafton_video_publicKey","xxx");
define("brafton_video_secretKey","xxx");

//define("brafton_apiKey","xxx");
define("hub_apiKey","xxx");
define("blog_id","xxx");
define("email","xxx");
define("author","xxx");
define("api_domain","http://api.brafton.com/");

Class brafton {

	function getArticles(){

		$blogs = new HubSpot_Blog(hub_apiKey);
		$content_type = 'json';

		$blogsInfo = $blogs->get_blogs(array('max'=>1), $content_type);

		foreach($blogsInfo as $blog){
			if($blog->guid==blog_id) $blog_id_set = 1;
		}
		if(!isset($blog_id_set)){
			echo "<pre>";
			var_dump($blogsInfo);
			echo "</pre>";
			die("blog_id is not set or not set correctly");
		}

		$params = array('max'=>99);

		$posts = $blogs->get_posts($params, blog_id, $content_type);

		$fh = new ApiHandler(brafton_apiKey, api_domain);
		$articles = $fh->getNewsHTML();

		foreach ($articles as $a) {
			$strPost = '';
			$createCat = array();
			$brafton_id = $a->getId();		
			$post_title = $a->getHeadline();
			if($this->post_exists($posts, $post_title)) continue;

			echo "POSTING: ".$post_title."<br>";

			$post_date = $a->getPublishDate();
			
			$post_content = $a->getText();

			$post_excerpt = $a->getExtract();
			$CatColl = $a->getCategories();

			$meta = $a->getHtmlMetaDescription();
			
				// Enter Author Tag
			$author = author;

			foreach ($CatColl as $category){
				$createCat[] = $category->getName();
			}

			var_dump($createCat);
			

				$photos = $a->getPhotos();  
			
				$image = $photos[0]->getLarge();
				
				$post_image = $image->getUrl();

				if(!empty($post_image)){
				
				$image_id = $photos[0]->getId();				

				$image_small = $photos[0]->getThumb();
				
				$post_image_small = $image_small->getURL();

				$strPost = $strPost.'<img src = "'.$post_image.'" alt ="" /><p>'.$post_content.'</p>' ;
			} else {
				$strPost= $strPost . $post_content;
			}

				//echo $post_image;

			echo $strPost."<br>";

			$tags = array();
			$count = 0;
			foreach($createCat as $cat){
				$tags[$count] = htmlspecialchars($cat);
				$count++;
			}

			$gui = $this->BraftonCreatePost($strPost, blog_id, $author, email, $post_title, $post_excerpt, $tags, $blogs);
			$this->BraftonPublishPost($gui, blog_id,$blogs);
		}

	}

	function getVideos(){

		
		$blogs = new HubSpot_Blog(hub_apiKey);
		$content_type = 'json';

		$blogsInfo = $blogs->get_blogs(array('max'=>1), $content_type);

		foreach($blogsInfo as $blog){
			if($blog->guid==blog_id) $blog_id_set = 1;
		}
		if(!isset($blog_id_set)){
			echo "<pre>";
			var_dump($blogsInfo);
			echo "</pre>";
			die("blog_id is not set or not set correctly");
		}

		$params = array('max'=>99);

		$posts = $blogs->get_posts($params, blog_id, $content_type);

		$baseURL = 'http://api.video.brafton.com/v2/';
		$videoClient = new AdferoVideoClient($baseURL, brafton_video_publicKey, brafton_video_secretKey);
		$client = new AdferoClient($baseURL, brafton_video_publicKey, brafton_video_secretKey);

		$photos = $client->ArticlePhotos();
		$photoURI = "http://pictures.directnews.co.uk/v2/";
		$photoClient = new AdferoPhotoClient($photoURI);
		$scale_axis = 500;
		$scale = 500;

		$feeds = $client->Feeds();
		$feedList = $feeds->ListFeeds(0,10);

		$articleClient=$client->Articles();

		//CHANGE FEED NUM HERE
		$articles = $articleClient->ListForFeed($feedList->items[0]->id,'live',0,100);

		foreach ($articles->items as $article) {
			$strPost = '';
			$thisArticle = $client->Articles()->Get($article->id);
			$throttle=1;
			$post_title = $thisArticle->fields['title'];
			if($this->post_exists($posts, $post_title) && $throttle > 0) continue;
			$throttle--;

			$embedCode = $videoClient->VideoPlayers()->GetWithFallback($article->id, 'redbean', 1, 'rcflashplayer', 1);

			$cleanEmbed = $embedCode->embedCode;

			

			echo "POSTING: ".$post_title."<br>";

			$post_date = $thisArticle->fields['lastModifiedDate'];
			
			$post_content = $thisArticle->fields['content'];

			$post_excerpt = $thisArticle->fields['extract'];
			
			$categories = $client->Categories();
			if(isset($categories->ListForArticle($article->id,0,100)->items[0]->id)){
				$categoryId = $categories->ListForArticle($article->id,0,100)->items[0]->id;
				$category = $categories->Get($categoryId);
				echo "<br><b>Category Name:</b>".$category->name."<br>";
			}

			$meta = $thisArticle->fields['extract'];
			
				// Enter Author Tag
			$author = author;

			$thisPhotos = $photos->ListForArticle($article->id,0,100);
			if(isset($thisPhotos->items[0]->id)) {		
				$photoId = $photos->Get($thisPhotos->items[0]->id)->sourcePhotoId;
				$photoURL = $photoClient->Photos()->GetScaleLocationUrl($photoId, $scale_axis, $scale)->locationUri;
				$photoURL = strtok($photoURL, '?');
			}

			$post_image = $photoURL;
			
			if(!empty($post_image)){
				$imgString = '<img class="alignRight" style="float: right;" src="'.$post_image.'" alt="" />';
				$strPost = $cleanEmbed .'<p>'.$post_content.'</p>' ;
			} else {
				$strPost= $strPost . $post_content;
			}

			echo $strPost."<br>";

			$tags[0] = htmlspecialchars($category->name);

			$gui = $this->BraftonCreatePost($strPost, blog_id, $author, email, $post_title, $post_excerpt, $tags, $blogs);	

		}
	}



	function BraftonCreatePost($post, $guid, $author, $email, $title, $summary, $tags, $blogs){
		echo "Trying to post<br>";
		$createPost = $blogs->create_post($guid, $author, $email, $title, $summary, $post, $tags);

		$beg = strpos($createPost, "<id>",88);
		$end = strpos($createPost, "</id>",$beg);
		$mid = substr($createPost, $beg+4, ($end - $beg-4));
		echo "beg: ".$beg."<br/>";
		echo "mid: ".$mid."<br/>";
		echo "end: ".$end."<br/>";
		//echo "createpost starts: ".$createPost." createpost ends<br/>";
		return $mid;
		}

      //Publish a blog post
	function BraftonPublishPost($postguid, $blogguid,$blogs){
		date_default_timezone_set('America/New_York');
		$future = date('G');
		if ($future < 23) {
			$future = $future + 1;
		} else { 
			$future = 1; 
		}
		$days = date('Y-m-d');
		$times = date('i:s');
		$publish_time = $days.'T'.$future.':'.$times.'Z';
		echo "before publishpost<br/>";
		echo $blogs->publish_post($postguid, $publish_time, 'true');
		echo "after publishpost<br/>";
		//return $mid;

	}

	function post_exists($posts, $post_title){
		foreach($posts as $post){
			if($post->title == $post_title) {
				echo $post_title." - exists<br>";
				return true;
			}

		}
		return false;
	}
}

$n = new brafton();


$n->getVideos();

//$n->getArticles();

?>

