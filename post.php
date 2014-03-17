<?php
class brafton_post{

    public $article_id;

    private $name;
    private $meta_description;
    private $slug;
    private $post_body;
    private $post_summary;
    private $topics;
    private $date;

    public function __construct($title,$meta_desc,$slug,$body,$summary,$topics = false,$video = false, $date = false){
        //creates blog post in draft format, updates it with desired data
        //returns article id, so it can be published if desired
        //3 step process recommended by hubspot api docs

        $this->name = $title;
        $this->meta_description = $meta_desc;
        $this->slug = $slug;
        $this->body = $body;
        $this->summary = $summary;
        $this->topics = $topics;
        $this->date = strtotime($date)*1000;



        $article = array(
            'name'=>$this->name,
            'content_group_id'=>blog_id,
        );

        $article_json = $this->import_post($article);

        $this->article_id = $article_json->id;

        $updated_article = array(
            'blog_author_id'=>author_id,
            'meta_description'=>$this->meta_description,
            'slug'=>$this->slug,
            'post_body'=>$this->body,
            'publish_immediately'=>true,
            "post_summary"=> $this->summary,
        );

        if($topics){
            $topics_string = "[";
            foreach($topics as $topic){
                $topics_string .= $topic . ',';
            }

            $topics_string .= "]";
            $updated_article['topic_ids'] = $topics;
        }

        if($video){

        $updated_article['head_html'] = '<link href="//vjs.zencdn.net/4.3/video-js.css" rel="stylesheet">
<script src="//vjs.zencdn.net/4.3/video.js"></script>';
        
        }
        
        $this->update_post($this->article_id,$updated_article);



    }

    private function import_post($jsonbody){
        $url =  'https://api.hubapi.com/content/api/v2/blog-posts?hapikey=' . hub_apiKey;
        $body = json_encode($jsonbody);

        return execute_post_request($url, $body,true);
    }

    private function update_post($article_id,$json_body){

        $url =  'https://api.hubapi.com/content/api/v2/blog-posts/' . $article_id . '?hapikey=' . hub_apiKey;

        $body = json_encode($json_body);

        return execute_put_request($url, $body,true);

    }

    public function publish_post($article_id,$publish_date = false){

        $url =  "https://api.hubapi.com/content/api/v2/blog-posts/$article_id/publish-action?portalId=" . portal  . "&hapikey=" . hub_apiKey;
        
        //echo $url;

        $json_body = array(
            'action'=>'schedule-publish',
        );
        
        $body = json_encode($json_body);

        $a = execute_post_request($url, $body,true);

        $updated_article = array(
            "publish_date"=> $this->date
        );
        
        $this->update_post($this->article_id,$updated_article);

    }

}   

?>
