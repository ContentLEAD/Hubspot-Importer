<?php  
    
    require_once 'SampleAPIClientLibrary/ApiHandler.php';
    // require_once 'hubspotClasses/class.leads.php';
    // require_once 'hubspotClasses/class.settings.php';
    // require_once 'hubspotClasses/class.events.php';
    // require_once 'hubspotClasses/class.leadnurturing.php';
    // require_once 'hubspotClasses/class.prospects.php';
    // require_once 'hubspotClasses/class.keywords.php';
    // require_once 'hubspotClasses/class.blog.php';
    require_once 'post.php';
    require_once 'RCClientLibrary/AdferoArticlesVideoExtensions/AdferoVideoClient.php';
    require_once 'RCClientLibrary/AdferoArticles/AdferoClient.php';
    require_once 'RCClientLibrary/AdferoPhotos/AdferoPhotoClient.php';


    define("brafton_video_publicKey", "");
    define("brafton_video_secretKey", "");
    define("brafton_apiKey", "");
    define("hub_apiKey","");
    define("blog_id","");
    define("author_id","");
    define("portal","");
    
    define("domain", ''); //ex http://api.brafton.com/
	
	$player = "under construction"; 
	
    //$params = array(
    //    'hapikey'=>hub_apiKey,
    //    'content_group_id'=>blog_id,
    //);

    //list_post_titles($params);

    //list_authors();

    //fetch hubspot blog existing articles, if any.  place them in an array for comparison.
    //to be done
    //articles from brafton feedzz

    if(!check_blog_id()){
        echo 'blog id not set or incorrect<br/><br/>';
         $params = array(
             'hapikey'=>hub_apiKey,
        );

        list_blogs($params);
        } else {
        //current date in milliseconds
        $post_time = time()*1000;

        //subtracting 30 days *24 hrs *60 Minutes *60 Seconds *1000 Milliseconds
        $post_time -= 30*24*60*60*1000;


        $params = array(
            'hapikey'=>hub_apiKey,
            'content_group_id'=>blog_id,
            'created__gte'=>$post_time,
            'limit'=>30,
            'order_by'=>'-created'
        );

        $titles = list_post_titles($params);

        $existing_topics = list_topics();

        import_articles($titles,$existing_topics);
		//import_videos($titles,$existing_topics);
	}
        
    function import_articles($titles,$existing_topics){

        $fh = new ApiHandler(brafton_apiKey, domain );
        $articles = $fh->getNewsHTML();
        $articles_imported = 0;

        foreach ($articles as $a) {
            $articles_imported++;
            if($articles_imported>3) break;
            //max of five articles importer

            $strPost = '';
            $createCat = array();
            $brafton_id = $a->getId();      
            $post_title = $a->getHeadline();

            // check against existing posts here.  Use title.

            if (compare_post_titles($post_title,$titles)) continue;

            echo "POSTING: ".$post_title."<br>";

            $post_date = $a->getPublishDate();
            
            $post_content = $a->getText();

            $post_excerpt = $a->getExtract();
            
            $words = str_word_count($post_title,1);

            $count = 0;
            $slug='';
			$num = count($words);
            foreach ($words as $word){
                $count++;
                $slug .= $word;
                if($count>6 || $count ==$num) break;
                $slug .= '_';
            }

            $CatColl = $a->getCategories();

            $meta = $a->getHtmlMetaDescription();
            
                // Enter Author Tag
            $author = author_id;

            foreach ($CatColl as $category){
                if(!$category) break;
                $createCat[] = $category->getName();
                //echo $category->getName() . '<br/>';
            }

			$article_topics = array();
		
			foreach($createCat as $brafton_cat){
				//echo "topic first loop: $brafton_cat <br/>";
                $topic_exists = false;
				$new_cat = false;
                foreach ($existing_topics as $topic) { 
				//echo "topic second loop: $topic <br/>";
                    if($brafton_cat == $topic){
			//echo "topic exists <br/>";
			//echo "brafton cat: ". $brafton_cat . '<br/>';  
                        //echo "topic: $topic <br/>";
			$article_topics[] = array_search( $topic, $existing_topics);
                        $topic_exists = true;
			break;
                    }

                }
				
				if(!$topic_exists){
					//echo "creating topic: $brafton_cat <br/>";
					$c = create_topic($brafton_cat,$existing_topics);
					$response = $c[0];
					$existing_topics = $c[1];
					$article_topics[] = $response->id;
				}
			}
                
            
                $photos = $a->getPhotos();  
            
                $image = $photos[0]->getLarge();
                
                $post_image = $image->getUrl();
                
                if(!empty($post_image)){

                    $image_id = $photos[0]->getId();                

                $image_small = $photos[0]->getThumb();

                $post_image_caption = "<div style='padding:6px;'>" . $photos[0]->getCaption() . "</div>";
                
                $post_image_small = $image_small->getURL();
                //<div style="background-color: #F9F9F9;border: 1px solid #CCCCCC; padding: 3px;font: 11px/1.4em Arial, sans-serif;margin: 0.5em 0.8em 0.5em 0; float:left;"> <img src="supernatural.jpg" style = "border: 1px solid #CCCCCC;vertical-align:middle; margin-bottom: 3px;"  alt="Google Logo" /> <br />Image Caption goes here. </div>
                $divcode = '<div style="width:300px;background-color: #F9F9F9; border-radius:10px; padding: 3px;font: 11px/1.4em Arial, sans-serif;margin: 0.5em 0.8em 0.5em 0.5em; float:right;">';
                $imgcode = '<img src="' . $post_image . '" style = "width:300px;vertical-align:middle; margin-bottom: 3px;"  alt="Google Logo" />'; 
                $div = $divcode . $imgcode . '<br/>' . $post_image_caption . '</div>';
                $strPost = $strPost.$div.$post_content;
                } else {
                    $strPost= $strPost . $post_content;
                }
              
            
                //echo $post_excerpt . '<br/>';

                //echo $post_image;

            //echo $strPost."<br>";

            /*

            create/publish posts
            tbd: topics (categories), not hotlinking images?            
        
            */



            $post = new brafton_post($post_title,$post_excerpt,$slug,$strPost,$post_excerpt,$article_topics,false,$post_date);

            $id = $post->article_id;

            $post->publish_post($id);  
            // broken...  what is time format of date on brafton feed?  will almost certainly need to convert.

        }
    }

        function import_videos($titles,$existing_topics){

        $params = array('max'=>99);

        $baseURL = 'http://api.video.castleford.com/v2/';
        $videoClient = new AdferoVideoClient($baseURL, brafton_video_publicKey, brafton_video_secretKey);
        $client = new AdferoClient($baseURL, brafton_video_publicKey, brafton_video_secretKey);
        $videoOutClient = $videoClient->videoOutputs();

        $photos = $client->ArticlePhotos();
        $photoURI = "http://pictures.video.castleford.com/v2/";
        $photoClient = new AdferoPhotoClient($photoURI);
        $scale_axis = 500;
        $scale = 500;

        $feeds = $client->Feeds();
        $feedList = $feeds->ListFeeds(0,10);

        $articleClient=$client->Articles();

        //CHANGE FEED NUM HERE
        $articles = $articleClient->ListForFeed($feedList->items[0]->id,'live',0,100);

        $articles_imported = 0;

       foreach ($articles->items as $a) {
            $articles_imported++;
            if($articles_imported>2) break;
            //max of five articles imported

            $thisArticle = $client->Articles()->Get($a->id);

            $strPost = '';
            $createCat = array();

            $post_title = $thisArticle->fields['title'];

            $post_date = $thisArticle->fields['lastModifiedDate'];
            
            $post_content = $thisArticle->fields['content'];

            $post_excerpt = $thisArticle->fields['extract'];

            $brafton_id = $a->id;

            // check against existing posts here.  Use title.

            if (compare_post_titles($post_title,$titles)) continue;

            echo "POSTING: ".$post_title."<br>";
            
            $words = str_word_count($post_title,1);

            $count = 0;
            $slug='';
            foreach ($words as $word){
                $count++;
                $slug .= $word;
                if($count>6) break;
                $slug .= '_';
            }
            //$meta = $a->getHtmlMetaDescription();
            
            // Enter Author Tag
            $author = author_id;
			
			$categories = $client->Categories();
			if(isset($categories->ListForArticle($a->id,0,100)->items[0]->id)){
				$categoryId = $categories->ListForArticle($a->id,0,100)->items[0]->id;
				$category = $categories->Get($categoryId);
				echo "<br><b>Category Name:</b>".$category->name."<br>";
				$createCat[] = $category->name;
			}

			$article_topics = array();
		
			foreach($createCat as $brafton_cat){
				echo "topic first loop: $brafton_cat <br/>";
                $topic_exists = false;
				$new_cat = false;
                foreach ($existing_topics as $topic) { 
				echo "topic second loop: $topic <br/>";
                    if($brafton_cat == $topic){
			echo "topic exists <br/>";
			echo "brafton cat: ". $brafton_cat . '<br/>';  
                        echo "topic: $topic <br/>";
			$article_topics[] = array_search( $topic, $existing_topics);
                        $topic_exists = true;
			break;
                    }

                }
				
				if(!$topic_exists){
					echo "creating topic: $brafton_cat <br/>";
					$c = create_topic($brafton_cat,$existing_topics);
					$response = $c[0];
					$existing_topics = $c[1];
					$article_topics[] = $response->id;
				}
			}
            
                // $photos = $a->getPhotos();  
            
                // $image = $photos[0]->getLarge();
                
                // $post_image = $image->getUrl();
                
                // if(!empty($post_image)){

                //     $image_id = $photos[0]->getId();                

                //     $image_small = $photos[0]->getThumb();
                    
                //     $post_image_small = $image_small->getURL();

                //     $post_excerpt = $post_excerpt.'<img src = "'.$post_image.'" alt ="" /><p>'.$post_content.'</p>' ;
                // }

                $presplash = $thisArticle->fields['preSplash'];
                $postsplash = $thisArticle->fields['postSplash'];
                                
                $videoList=$videoOutClient->ListForArticle($brafton_id,0,10);
                $list=$videoList->items;

                if ($player == "atlantis")
                    $embedCode = sprintf( "<video id='video-%s' class=\"ajs-default-skin atlantis-js\" controls preload=\"auto\" width='512' height='288' poster='%s' >", $brafton_id, $presplash ); 
                
                else
                    $embedCode = sprintf( "<video id='video-%s' class='video-js vjs-default-skin' controls preload='auto' width='512' height='288' poster='%s' data-setup src='%s' >", $brafton_id, $presplash, $path ); 

                foreach($list as $listItem){
                    $output=$videoOutClient->Get($listItem->id);
                    //logMsg($output->path);
                    $type = $output->type;
                    $path = $output->path; 
                    $resolution = $output->height; 
                    $source = generate_source_tag( $path, $resolution );
                    $embedCode .= $source; 
                }       
                $embedCode .= '</video>';
                //old code
                //$embedCode = $videoClient->VideoPlayers()->GetWithFallback($brafton_id, 'redbean', 1, 'rcflashplayer', 1);
                
                if ($player == "atlantis"){
                    $script = '<script type="text/javascript">';
                    $script .=  'var atlantisVideo = AtlantisJS.Init({';
                    $script .=  'videos: [{';
                    $script .='id: "video-' . $brafton_id . '"';
                    $script .= '}]';
                    $script .= '});';
                    $script .=  '</script>';
                    $embedCode .= $script; 
                }

                $strPost = $embedCode . $post_content;

                //echo $post_image;

            //echo $strPost."<br>";

            /*

            create/publish posts
            tbd: topics (categories), not hotlinking images?            
        
            */



            $post = new brafton_post($post_title,$post_excerpt,$slug,$strPost,$post_excerpt,$article_topics,true);

            $id = $post->article_id;

            $post->publish_post($id);  
            // broken...  what is time format of date on brafton feed?  will almost certainly need to convert.

        }
        
    }




    //end of loop

    function list_blogs($params){
        $url = 'https://api.hubapi.com/content/api/v2/blogs';

        $url_params=params_to_string($params);
        
        $blogsInfo = execute_get_request($url . $url_params);

        echo "blog names and id's:<br/>";

        foreach ($blogsInfo->objects as $blog){
            echo $blog->html_title . ' - ' . $blog->id . '<br/>';
        }
    }

    function check_blog_id(){
        
        $blog_id_set=false;

        $url = 'https://api.hubapi.com/content/api/v2/blogs';

        $params = array(
            'hapikey'=>hub_apiKey,
        );

        $url_params=params_to_string($params);
        
        $blogsInfo = execute_get_request($url . $url_params);


        foreach($blogsInfo->objects as $blog){
                if($blog->id==blog_id) $blog_id_set = 1;
        }

        return $blog_id_set;

    }

    function list_post_titles($params){   
        //list post titles
        //
        //array of parameters should be like this:
        // $params = array(
        //     'hapikey'=>hub_apiKey,
        //     'content_group_id'=>blog_id,
        //);
        //hapikey needs to be first

        $url = 'https://api.hubapi.com/content/api/v2/blog-posts';

        $url_params = params_to_string($params);

        $postsInfo = execute_get_request($url . $url_params);

        $titles = array();

        foreach($postsInfo->objects as $post){
            $titles[] = $post->name;
            //echo $post->created;
        }

        return $titles;
    }

    function compare_post_titles($needle,$haystack){
        //accepts string of title and array of titles (strings)
        $match = false;
        //echo "needle " . $needle . "<br/>";
        foreach($haystack as $hay){
            //echo "hay: " . $hay . "<br/>";
            if($needle == $hay) $match=true;
        }

        return $match;
    }

    function list_authors(){
        $url = 'https://api.hubapi.com/blogs/v3/blog-authors?hapikey=' . hub_apiKey . '&casing=snake_case';
        
        $authorsInfo = execute_get_request($url);

        echo "blog author names and id's:<br/>";

        foreach ($authorsInfo->objects as $author){
            echo $author->full_name . ':  ' . $author->id;
        }
    }

	function list_topics(){
        $url = 'https://api.hubapi.com/blogs/v3/topics?hapikey=' . hub_apiKey . '&casing=snake_case';
        
        $topicsInfo = execute_get_request($url);

        //echo "blog topics names and id's:<br/>";

        $topic_array = array();

        foreach ($topicsInfo->objects as $topic){
            //echo $topic->name . ':  ' . $topic->id . '<br/>';
            //Convert ID to string to avoid max int issue with large topic IDs
            $topic_id_str = strval($topic->id);
            $topic_array[$topic_id_str]=$topic->name;
        }
        return $topic_array;
    }

    function compare_topics($needle, $haystack){
        
        $match = false;

        foreach ($haystack as $hay){
            if($hay == $needle) $match = key($haystack);
        }
        return $match;
    }
	
    function create_topic($topic,$existing_topics){
        //global $existing_topics;

        $url = 'https://api.hubapi.com/blogs/v3/topics?hapikey=' . hub_apiKey . '&casing=snake_case';

        $params = array(
            'name' => $topic,
            'slug' => htmlspecialchars($topic),
        );

        $json = json_encode($params);

        $response = execute_post_request($url, $json);

        $existing_topics[$response->id] = $topic;
		
		$return = array(
			'0'=>$response,
			'1'=>$existing_topics,
		);

        return $return;
    }
	

    function params_to_string($params){
        $url_params = "?";
        foreach(array_keys($params) as $key){
            $url_params .= $key . '=' . $params[$key] . '&';
        }
        return $url_params;
    }




    function execute_get_request($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "haPiHP default UserAgent");  // new
       
        $output = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ( $errno > 0) {
            throw new Exception('cURL error: ' + $error);
        } else {
            return json_decode($output);
        }
    }



    function execute_post_request($url, $body, $formenc=FALSE) {  //new
        
        // intialize cURL and send POST data
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "haPiHP default UserAgent");  // new
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Content-Type: application/json')                                                                       
        );
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
       // if ($formenc)   // new
       //     curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded')); // new
        
        $output = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch); 
        if ($errno > 0) {
            echo 'cURL error: ' . $error;
        } else {
            return json_decode($output);
        }
    }

    function execute_put_request($url, $body, $formenc=FALSE) {  //new
        
        // intialize cURL and send POST data
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "haPiHP default UserAgent");  // new
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Content-Type: application/json')                                                                       
        );
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
       // if ($formenc)   // new
       //     curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded')); // new
        
        $output = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch); 
        if ($errno > 0) {
            echo 'cURL error: ' . $error;
        } else {
            return json_decode($output);
        }
    }

    /* video updates*/
    function generate_source_tag($src, $resolution)
    {
        $tag = ''; 
        $ext = pathinfo($src, PATHINFO_EXTENSION); 

        return sprintf('<source src="%s" type="video/%s" data-resolution="%s" />', $src, $ext, $resolution );
    }

?>
