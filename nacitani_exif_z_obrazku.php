<?php

  /*
   *
   * Ukazka fce nacitajici z obrazku EXIF informace
   *
   */ 

   public function getExifs($filename){
      // nastavime si adresu souboru
      $image = '/www/doc/www.photomiyako.com/.../uploaded/'  . $filename;
      // nacteme EXIF data
      $exif = exif_read_data($image, 0, true);
      // pripravime promenne
      $set_width = ""; $set_height = ""; $set_model = ""; $set_format = ""; $set_author = ""; $set_xresolution = ""; $set_yresolution = ""; $set_ifdatetime = "";
      $set_exdatetime = ""; $set_gpsx = 0; $set_gpsy = 0; $set_gpsxr = 0; $set_gpsyr = 0;
      $tags = array();
      $it=0;
      // skript pro vycteni konkretnich dat z ruznych formatu EXIF
      foreach($exif as $key => $section){
         if($key=="IFD0"){
            foreach($section as $name => $val){
               if($name=='ImageWidth'){ $set_width = $exif['IFD0']['ImageWidth']; }
               if($name=='ImageLength'){ $set_height = $exif['IFD0']['ImageLength']; }
               if($name=='Model'){ $set_model = $exif['IFD0']['Model']; }
               if($name=='XResolution'){ $set_xresolution = $exif['IFD0']['XResolution']; }
               if($name=='YResolution'){ $set_yresolution = $exif['IFD0']['YResolution']; }
               if($name=='DateTime'){ $set_ifdatetime = $exif['IFD0']['DateTime']; }
               if($name=='ExtensibleMetadataPlatform'){ 
                  $metadata = $exif['IFD0']['ExtensibleMetadataPlatform'];
                        while(strpos($metadata,'<rdf:li>')){
                           $in = strpos($metadata,'<rdf:li>')+8;
                           $end = strpos($metadata,'</rdf:li>');
                           $out = $end - $in;
                           $tag = substr($metadata,$in,$out);
                              if(strpos($tag,'<') or strpos($tag,'>')){
                                 $end += 5;
                                 $metadata = substr($metadata,$end);
                              }else if(strpos($tag,',')){
                                 $tag = substr($tag, 0, strpos($tag,','));
                                 $end += 5;
                                 $metadata = substr($metadata,$end);
                                 if($tag!='255' and $tag!='0'){
                                    $tags[$it] = $tag;
                                    $it++;
                                 }
                              }else{
                                 $end += 5;
                                 $metadata = substr($metadata,$end);
                                 if($tag!='255' and $tag!='0'){
                                    $tags[$it] = $tag;
                                    $it++;
                                 }
                              }
                        }
               }
            }
            
         }
         if($key=="GPS"){
            foreach($section as $name => $val){
               if($name=='GPSLatitude'){ $set_gpsx = 1; }
               if($name=='GPSLatitudeRef'){ $set_gpsxr = 1; }
               if($name=='GPSLongitude'){ $set_gpsy = 1; }
               if($name=='GPSLongitudeRef'){ $set_gpsyr = 1; }
            }
         }
         if($key=="EXIF"){
            foreach($section as $name => $val){
               if($name=='DateTimeOriginal'){ $set_exdatetime = $exif['EXIF']['DateTimeOriginal']; }
            }
         }
         if($key=="COMPUTED"){
            foreach($section as $name => $val){
               if($name=='Height' and $set_height==''){ $set_height = $exif['COMPUTED']['Height']; }
               if($name=='Width' and $set_width==''){ $set_width = $exif['COMPUTED']['Width']; }
               if($name=='Copyright'){ 
                  $set_author = $exif['COMPUTED']['Copyright']; 
                  if(strpos($set_author,',')){
                                 $set_author = substr($set_author, 0, strpos($set_author,','));
                  }
               }
            }
         }
         if($key=="FILE"){
            foreach($section as $name => $val){
               if($name=='MimeType' and $exif['FILE']['MimeType']=='image/tiff'){ $set_format = 'tiff'; }
               if($name=='MimeType' and $exif['FILE']['MimeType']=='image/jpeg'){ $set_format = 'jpg'; }
            }
         }

      }
      // finalizujeme tvar GPS souradnic
   $gpsx = "";
   $gpsy = "";
   if($set_gpsx==1 and $set_gpsxr==1){
      $gpsx = $this->decodeGPS($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']);
   }
   if($set_gpsy==1 and $set_gpsyr==1){
      $gpsy = $this->decodeGPS($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']);      
   }
   $dpi="";
   // pokud mame data zabalime do pole a vratime
   if($set_xresolution!='' and $set_yresolution!='' and $set_yresolution==$set_xresolution){ $dpi=substr($set_xresolution,0,strpos($set_xresolution,'/')); }
      $data = array(
         'width' => $set_width,
         'height' => $set_height,
         'model' => $set_model,
         'format' => $set_format,
         'author' => $set_author,
         'dpi' => $dpi,
         'ifdatetime' => $set_ifdatetime,
         'tags' => $tags,
         'exdatetime' => $set_exdatetime,
         'GPSx' => $gpsx,
         'GPSy' => $gpsy,
         'Exif' => $exif,
      );
      return $data;
   }