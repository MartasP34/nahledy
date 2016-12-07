<?php

    /*
     *
      * Ukazka implementace funkce chranici proti prepisovani adminama pri soucasnem pripojeni do systemu
      * Pokud vstupuji do editace objektu (fotky), ktery v uplynulych hodinach nacetl v administraci jiny admin, budou oba upozorneni hlasenim
      *
      */




// MODEL PHOTOS REPOSITORY

class PhotosRepository extends Nette\Object
{
   private $tb_pht = 'photos';
   private $tb_tgs = 'tags';
   private $tb_szs = 'sizes';
   private $tb_ptc = 'ptcons';
   private $tb_lcn = 'licences';
   private $tb_stm = 'sizetemplates';

   private $db;

   public function __construct(\DibiConnection $connection)
   {
         $this->db = $connection;
   }
   public function owsLock($user_id, $section, $item_id)
   {
      $pole = json_decode(file_get_contents('/www/doc/www.photomiyako.com/www/admin/files/owsCheck/overwritecheck.json'), true);
      $ac_dt = date('Y-m-d H:i:s', Time());
      $con_dt = date('Y-m-d H:i:s', strtotime('-10 hours', strtotime($ac_dt)));
// kontrola
      $kontrola = 'ok';
      foreach($pole as $auser_id=>$arr){
         if($auser_id!=$user_id AND $arr['datetime'] > $con_dt AND $arr['section'] == $section AND $arr['item_id'] == $item_id) $kontrola = $this->db->select('admin_login')->from('admins')->where('admin_id=%i',$auser_id)->fetchSingle();
      }
// zapis
      $pole[$user_id]['datetime'] = $ac_dt;
      $pole[$user_id]['section'] = $section;
      $pole[$user_id]['item_id'] = $item_id;
      file_put_contents("/www/doc/www.photomiyako.com/www/admin/files/owsCheck/overwritecheck.json",json_encode($pole));
// hotovo vratime vysledek kontroly
      return $kontrola;
   }

   public function getAllPhotos()
   {
      return $this->db->select('*')->from($this->tb_pht)->fetchAll();
   }
   public function getPhotosByTag($tid)
   {
      return $this->db->select('*')
      ->from($this->tb_ptc)
      ->leftJoin($this->tb_pht)
      ->on('ptcons.photo_id=photos.photo_id')
      ->where('tag_id=%i',$tid)->fetchAll();
   }
}





// CONTROLER PHOTOS PRESENTER

<?php

/**
 * PHOTOS presenter, by Martin Pazdera, 2014.
 */
use Nette\Application\UI\Form;   
class PhotosPresenter extends BasePresenter
{
   private $photos;
   private $users;
   private $settings;
   private $photo;
   private $exifs;
   private $filename;
   private $datas;
   private $id;
   private $langs;
   private $languages;
   private $tag;
   private $tag_temp;
   private $res;
   public $aSession;

   protected function startup()
   {
      $user=$this->getUser();
      if($user->isLoggedIn()){
//         if($user->getRoles==1){
         parent::startup();
         $this->photos =  $this->context->photosRepository;
         $this->users =  $this->context->userRepository;
         $this->settings =  $this->context->settingsRepository;
         $this->langs =  $this->settings->getAllLanguagesSelect();
         $this->languages =  $this->settings->getAllLanguages();
         $this->aSession = $this->getSession('aSession');
         if(!$this->aSession->data) $this->aSession->data = array();
         $this->aNode = 'photos';
         $this->aSubnode = 'none';

//          }
      }else{
         $this->redirect('Sign:in');
      }
   }

   // FUNKCE PRO KONTROLU
   public function owsCheck($section,$item_id)   
   {
      $user=$this->getUser();
      $uid=0; $ows=0;
      if($user->isLoggedIn()) $ows = $this->photos->owsLock($user->getId(),$section,$item_id);
      if($ows!='ok') $this->flashMessage('ATTENTION please. This item has been edited by admin '.strtoupper($ows).' now. Be carefull.');      
   }
 
   public function actionDefault(){
               $this->aSubnode = 'photos';
   }
   public function renderDefault($page=1,$search='no',$format='all',$orient='all',$status='all',$licence='all',$author=null,$view=0)
   {
      $photos = $this->photos->getFilteredPhotos($search, $format, $orient, $status, $licence, $author, 100, $page);
      $this->template->authors = $this->users->getAuthorsList();
      $this->template->photos = $photos['data'];
      if($search=='no') $search="";
      if($format=='all') $format="0";         
      $this->template->search = $search;;
      $this->template->format = $format;
      $this->template->orient = $orient;
      $this->template->status = $status;
      $this->template->licence = $licence;
      $this->template->author = $author;
      $this->template->licences = $this->photos->getAllLicences();
      $this->template->view = $view;
      $counted = count($photos['count']);
      $this->template->counted = $counted;
      $this->template->page = $page;
      $this->template->pages = ceil ($counted / 100) + 1;
   }
   
   public function actionPhoto($pid){
         $this->aSubnode = 'photos';
   }
   public function renderPhoto($pid)
   {
      $this->owsCheck('1',$pid);        # PRI OTEVRENI EDITACE VOLAME KONTROLU OWS CHECK
      $this->photo = $this->photos->getPhoto($pid);
      $this->template->clipboard = $this->photos->getTagsClipboard($pid);
      $this->photo = $this->photos->getPhoto($pid);
      $this->template->photo = $this->photo;
      $this->template->sizes = $this->photos->getPhotoSizes($pid);
      $this->template->tags = $this->photos->getPhotoTags($pid);
      $this->template->licences = $this->photos->getAllLicences();
      $filename = "/www/doc/www.photomiyako.com/.../files/".$this->photo['photo_hash'];
      $this->template->filesz = round(filesize($filename)/1000000,2);
      $this->template->articles = $this->photos->getArticles($pid);
      $this->template->langs = count($this->langs);
      $titles = array();
      $i=0;
      foreach($this->languages as $language)
      {
         $titles[$i]['id'] = $language->id;
         $titles[$i]['lang'] = $language->title;
         $titles[$i]['title'] = $this->photos->getPhotoTitle($pid,$language->id);
         $i++;
      }
      $this->template->titles = $titles;
   }


}