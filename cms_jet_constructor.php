<?php

/***
   * Ukazka funkce Contructor majici v mem CMS na svedomi vykresleni stranek na front-endu 
   *
   ***/

/**
   * CMS JET 1.2
   * Default Presenter.
   *
   * @author     Martin Pazdera
   * @copyright  Copyright © 2011 Martin Pazdera
   *
   * Front Default
   */
   
   
class Front_DefaultPresenter extends Front_BasePresenter
{

   public $c_id;
   public $constructor_code;
   public $html_construction;
   public $node;
   public $mylog;
   public $lang;
   public $mynode;
   public $general;

 	protected function startup()
	{
		parent::startup();
		$this->mylog = new logModel();
		$this->general = new generalModel();
	}



   public function renderDefault($lang=0)
   {
   	$nb = new newsboxesModel();
   	$nb_settings = $this->general->get_newsboxes_settings();

   	$this->template->lang = $lang;
   	$this->template->uvdarticle = $this->giveMeArticle(71);
   	$this->template->sidepanel = $this->giveMeArticle(275);
   }


/**
  *
  * CONSTRUCTOR - front - CMS Jet
  *
  */
   public function actionConstructor($id, $lang)
   {
      // Potrebne modely
      $model = new constructorModel(); 
      $nodes = new FrontNodesModel();  
      $gennodes = new NodesModel();  

      // nastaveni jazykove mutace         
      $lng=0;
      if(!$lang)
      {
         $lng=0;
      }else{
          $mylang = $this->general->get_language_id($lang);
          $lng=$mylang['id'];
      }

      // informace o zapnutych komponentach systemu a kontrola dostupnosti jazykove mutace
      $act = $this->general->get_activations();
      if($act['langs']==0) $lng=0;
         
      $this->lang = $lng;
      $this->node = $nodes->getNode($id,$lng);

      // overeni zverejnenosti koncove stranky
      if($this->node['visible']==0) $this->redirect('default');
      
      // pristup pouze pod prihlasenim
      $this->c_id = $id;
      if($this->node['viponly']==1){ // pokus je stranka urcena pouze prihlasenym uzivatelum
          if(!$this->user->isLoggedIn()){
           	$this->redirect('default:viponly');
          }else{
            $vips = new VipsModel();
            $vipData = $vips->getVipData($this->user->id);
            if($vipData['access']==1){

            	$perms = substr_count($vipData->permitions,$this->c_id);
            	$perms += substr_count($vips->get_group_permitions($vipData->group_id), $this->c_id);
            	if($perms==0){
                  $this->flashMessage('Přístup omezen !');
                  $this->redirect('default:vipdeny');
            	}
            }
          }
      }


      // GENEROVANI HTML STRANKY
      $constructor_code = $model->getConstructorElements($this->c_id,$lng);
      $html = "";
      $i=1;
      foreach($constructor_code as $construction){
         switch($construction->element_type){
             case 1: // prehled
               $view = $this->giveMeView($construction->element_id, $lng);
               $html .= $view;
             break;
             case 2: // clanek (Z WISIWIG EDITORU)
               $article = $this->giveMeArticle($construction->element_id);
               $html .= $article->article;
             break;

             case 4: // album
               $album = $this->giveMeAlbum($construction->element_id);
               $html .= $album;
             break;

             case 6: // soubor
               $download = $this->giveMeFile($construction->element_id);
               $html .= $download;
             break;

             case 7: // prehled alb
               $html .= $this->giveMeAview($construction->element_id);;
             break;

             case 12: // cara
               $html .= "<hr />";
             break;

             default:
               $html .= $construction->element_type . " / id: " . $construction->element_id . "| ";
             break;
         }

          $i = $i+1;
     }
      $this->html_construction = $html;

   }
   
   
   public function renderConstructor()
   {

      $this->template->node = $this->node;
      $this->template->html = $this->html_construction;
      $this->template->lang = $this->lang;

   }
   
   
   

// ODBAVENI PRVKU


      public function giveMeArticle($id){
          $articles = new articlesModel();
          return $articles->getArticle($id);
      }
      
      
      
      public function giveMeFile($id){
          $downloads = new downloadsModel();
          $file = $downloads->getDownloadFile($id);
          $html = "<li>Soubor ke stažení: <a href=\"" . $file->file . "\">" . $file->title . "</a></li>";

          return $html;
      }
      
      
      
      public function giveMeView($id, $lang=0){
          
          // nacteme modely
          $views = new viewsModel();
          $nodes = new nodesModel();
          
          // ziskame data o danem prehledu podstranek
          $view = $views->getView($id);
          
          // nacteme sablonu prehledu
          $template = $views->getTemplate($view->templates_id);
          
          $html="";

          // ziskame subnody 
          $myNodes = $nodes->get_nodes4view($view->node_id, $view->number, $view->order_by, $view->order_desc, $lang);
          
          // zacatek sablony
          $html .= $template->view_start;
          
          // polozky (subnody a jejich vykresleni)
          $i = 1;
          $counter = 0;
          foreach($myNodes as $node){
                  if($i==1){
                      $html .= $template->row_start;
                  }
                  $html .= $this->giveMeItem($template,$node,$lang);
                  if($i==$template->iPr){
                      $html .= $template->row_end;
                      $i = 0;
                  }
               $i++;
               $counter++;
          }

          // konec sablony
          $html .= $template->view_end;
          
          return $html;
      }
      
      

      public function giveMeItem($template, $node, $lang){

         // nacteme modely
         $nodes = new nodesModel();

         // zacatek polozky podle sablony
         $template_start =  $template->item_start;

         // nahrada promennych
         if(substr_count($template_start,'$URL$')!=0){ // TITLE
            $template_start = str_replace('$URL$', $this->general->get_web_url() . "/" . $nodes->getUrl($node->node_id,$lang),$template_start);
         }
         if(substr_count($template_start,'$TITLE$')!=0){ // TITLE
            $template_start = str_replace('$TITLE$',$node->title,$template_start);
         }
         if(strpos($template_start,'$IMG$')!=0){ // IMAGE
            $template_start = str_replace('$IMG$','/files/avatars/' . $node->img,$template_start);
         }
         if(strpos($template_start,'$PEREX$')!=0){ // PEREX
            $template_start = str_replace('$PEREX$',$node->perex,$template_start);
         }
         if(strpos($template_start,'$DATETIME$')!=0){ // PEREX
            $template_start =  str_replace('$DATETIME$',date("j. n. Y",strtotime($node->datetime)),$template_start);
         }

         // zaver polozky ze sablony
         $template_end =  $template->item_end;

         // nahrada promennych
         if(strpos($template_end,'$URL$')!=0){ // TITLE
            $template_end =  str_replace('$URL$', $general->get_web_url() . "/" . $nodes->getUrl($node->node_id,$lang),$template_end);
         }
         if(strpos($template_end,'$TITLE$')!=0){ // TITLE
            $template_end =  str_replace('$TITLE$',$node->title,$template_end);
         }
         if(strpos($template_end,'$IMG$')!=0){ // IMAGE
            $template_end =  str_replace('$IMG$','/files/avatars/' . $node->img,$template_end);
         }
         if(strpos($template_end,'$PEREX$')!=0){ // PEREX
            $template_end =  str_replace('$PEREX$',$node->perex,$template_end);
         }
         if(strpos($template_end,'$DATETIME$')!=0){ // PEREX
            $template_end =  str_replace('$DATETIME$',date("j. n. Y",time($node->datetime)),$template_end);
         }

         // kompletace html kodu polozky k vraceni         
         $html = "";
         $html .= $template_start;
         $html .= $node->title;
         $html .= $template_end;
         return $html;
      }
      
      

      public function giveMeAlbum($id)
      {
          //nacteme modely
          $albums = new albumsModel();
          
          //ziskame sablony alb
          $templates = $this->general->get_albums_templates();
          
          // ziskame fotky daneho alba
          $photos = $albums->getPhotos($id);
          
          $html = "";
          
          // nahrada promennych u kazdeho snimku
          foreach($photos as $photo)
          {
            $item = $templates['item'];
            if(strpos($item,'$URL$')!=0){ // TITLE
               $item =  str_replace('$URL$',"/album/photo/" . $id . "_" . $photo->id,$item);
            }
            if(strpos($item,'$TITLE$')!=0){ // TITLE
               $item =  str_replace('$TITLE$',$photo->perex,$item);
            }
            if(strpos($item,'$IMG$')!=0){ // IMAGE
               $item =  str_replace('$IMG$',$photo->img,$item);
            }
            if(strpos($item,'$PEREX$')!=0){ // PEREX
               $item =  str_replace('$PEREX$',$photo->perex,$item);
            }
            if(strpos($item,'$DATETIME$')!=0){ // PEREX
               $item =  str_replace('$DATETIME$',$photo->datetime,$item);
            }
          $html .= $item;
          }
          
          // kompletace kodu alba
         $html = str_replace('$ITEMS$', $html, $templates['view']);


         return $html;

      }


}
