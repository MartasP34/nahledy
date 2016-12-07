<?php

/***
   *
   * Ukazka rekurzi vykreslujicich strom podstranek v administraci, dole prikladam i jednodussi verzi, ktera generuje sitemapu
   *
   ***/
   
   
   
/** NODES Draw */

   public function drawNodes($selected_node)
   {
      $nodes = new NodesModel(); // deklarujeme si model
      if($selected_node==0)
      {
         $html = $this->mainNodesDraw();
      }else{
         $route = $this->getBranch($selected_node); // pustime prvni rekurzi a ziskame si trasu
         $html = $this->nodesRecursion($route,1,0,$selected_node); // pustime druhou rekurzi a ziskame html
      }

      return $html;
   }


/**
 *
 * NODES RECURSIONS
 *
 */

   public function getBranch($code)
   {
         $pole=explode(";", $code);
         $i=1;
         $mojeid="";
         foreach ($pole as $cast)
         {
            if ($i==1)
            {
               $mojeid = $cast;
            }
            $i++;
         }
         $nodes = new NodesModel();
         $mynode = $nodes->getNode($mojeid,0);

         if ($mynode->level!=1)
         {
         	$code = $mynode->parent_id . ";" . $code;
            $code = $this->getBranch($code);     // RECURSION
         }
         return $code;
   }

   public function nodesRecursion($code, $level, $parent_id, $selected_node)
   {
      $html = "";
      $general = new GeneralModel();
      $url = $general->get_web_url();
         $pole=explode(";", $code);
         $i=1;
         $mojeid="";
         foreach ($pole as $cast)
         {
            if ($i==$level)
            {
               $mojeid = $cast;
            }
            $i++;
         }

      if ($level==1)
      {
         $this_nodes = $this->get_main_nodes(); // ziskame hlavni nody
      }else{
         $this_nodes = $this->get_child_nodes($parent_id); // ziskame nody od rodice
      }
      $stop = 0;

      $html .= "        <ul>\n";

      foreach ($this_nodes as $node) // vypiseme nody az po vybranou (vc.)      
      {
         if ($stop==0)
               {
                     if(strlen($node->title)>20){
                        $dots = "...";
                     }else{
                        $dots = "";
                     }
                     if($node->node_id==$selected_node){
                        $html .= "                 <li><strong><a href=\"". $url . "/admin/nodes/constructor?constructor_id=" . $node->node_id . "\" title=\"" . $node->title . "\">" . substr($node->title,0,20) . $dots . "</a></strong></li>\n";
                     }else{
                        $html .= "                 <li><a href=\"". $url . "/admin/nodes/constructor?constructor_id=" . $node->node_id . "\" title=\"" . $node->title . "\">" . substr($node->title,0,20) . $dots . "</a></li>\n";
               }     }
       	if ($node->node_id==$mojeid)
             	{
             	    $stop = 1;
             	}
      }
      $level++;
      if ($level<=$i)
      {
       	      $html .= $this->nodesRecursion($code, $level, $mojeid, $selected_node); // REKURZE
      }
      $start = 0;
      foreach ($this_nodes as $node) // vypiseme nody od vybrane      
      {
         if ($start==1)
               {
                     $html .= "                 <li><a href=\"". $url . "/admin/nodes/constructor?constructor_id=" . $node->node_id . "\">". $node->title . "</a></li>\n";                     
               }
       	if ($node->node_id==$mojeid)
             	{
             	    $start = 1;
             	}
      }      

      $html .= "        </ul>\n";

      return $html;
   }

   public function mainNodesDraw()
   {
         $general = new GeneralModel();
         $url = $general->get_web_url();
         $html = "             <ul>";
         $this_nodes = $this->get_main_nodes(); // ziskame hlavni nody
         foreach ($this_nodes as $node) // vypiseme nody az po vybranou (vc.)
         {
                     $html .= "                 <li><a href=\"". $url . "/admin/nodes/constructor?constructor_id=" . $node->node_id . "\">". $node->title . "</a></li>\n";
         }
         $html .= "             </ul>";
         return $html;
   }
   
   
   

/**
  *
  *
  * Simple SITEMAP txt
  *
  *
  */

   public function getSitemap($lang)
   {
      $html = "";
      $html .= $this->T4SitemapRecursion(0, $lang);
   	return $html;
   }
  public function T4SitemapRecursion($parent_id, $lang)
  {
      $html = "";

      $general = new GeneralModel();
      $url = $general->get_web_url();

      $this_nodes = $this->get_child_nodes4S($parent_id, $lang); // ziskame nody od rodice

      $html .= "";

      foreach ($this_nodes as $node) // vypiseme nody od vybrane
      {
            if($node->visible==1)
            {
                  if($lang==0){
                     $html .= $url . "/" . $this->getUrl($node->node_id,$lang) . "\n";
                  }else{
                     $html .= $url . "/" . $this->getUrl($node->node_id,$lang) . "\n";
                  }

            }
            $html .= $this->T4SitemapRecursion($node->node_id, $lang);
      }

      $html .= "";

      return $html;

  }
