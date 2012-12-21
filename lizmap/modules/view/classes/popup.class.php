<?php
/**
* Class with methods relative to Lizmap Popups
* @package   lizmap
* @subpackage view
* @author    3liz
* @copyright 2011 3liz
* @link      http://3liz.com
* @license    Mozilla Public License : http://www.mozilla.org/MPL/
*/

class popup{

  /**
  * Replace a feature attribute value by its html representation
  *
  * @param string $attributeName Feature Attribute name.
  * @param string $attributeValue Feature Attribute value.
  * @param string $repository Lizmap Repository.
  * @param string $project Name of the project.
  * @param string $popupFeatureContent Content of the popup template (created by lizmap plugin) and passed several times. IF false, return only modified attribute.
  * @return string The html for the feature attribute.
  */
  public function getHtmlFeatureAttribute($attributeName, $attributeValue, $repository, $project, $popupFeatureContent=Null){

    // Force $attributeValue to be a string
    $$attributeName = (string)$$attributeName;
    $attributeValue = (string)$attributeValue;
    
    // Regex to replace links, medias and images
    $urlRegex = '/(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/';
    $emailRegex = '/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/';
    $imageRegex = '/\.(jpg|jpeg|png|gif|bmp)$/i';
    $mediaRegex = '/^(\/)?media\//';
    $mediaTextRegex = '/\.(txt|htm|html)$/i';

    // Remote urls and images
    if(preg_match($urlRegex, $attributeValue)){
      if(preg_match($imageRegex, $attributeValue))
        $attributeValue = '<img src="'.$attributeValue.'" width="300" border="0"/>';
      else
        $attributeValue = '<a href="'.$attributeValue.'" target="_blank">'.$attributeValue.'</a>';
    }

    // E-mail
    if(preg_match($emailRegex, $attributeValue))
      $attributeValue = '<a href="mailto:'.$attributeValue.'"</td></tr>';

    // Media = file stored in the repository media folder
    if(preg_match($mediaRegex, $attributeValue)){
      $mediaUrl = jUrl::getFull(
        'view~media:getMedia',
        array('repository'=>$repository, 'project'=>$project, 'path'=>$attributeValue),
        0,
        $_SERVER['SERVER_NAME']
      );

      // Display if it is an image
      if(preg_match($imageRegex, $attributeValue)){
        $attributeValue = '<img src="'.$mediaUrl.'" width="300" border="0"/>';
      }

      // If a file containing text or html : get its content
      else if(preg_match($mediaTextRegex, $attributeValue)){
        $data = '';
        // Get full path to the file
        jClasses::inc('lizmap~lizmapConfig');
        $lizmapConfig = new lizmapConfig($repository);
        $repositoryPath = realpath($lizmapConfig->repositoryData['path']);
        $abspath = realpath($repositoryPath.'/'.$attributeValue);
        if(preg_match("#^$repositoryPath/media/#", $abspath) and file_exists($abspath)){
          $data = jFile::read($abspath);
        }

        // Replace images src by full path
        $iUrl = jUrl::get(
          'view~media:getMedia',
          array('repository'=>$repository, 'project'=>$project)
        );
        $data = preg_replace(
          '#src="(.+(jpg|jpeg|gif|png))"?#i',
          'src="'.$iUrl.'&path=$1"',
          $data
        );
        $attributeValue = $data;
      }

      // Else just write a link to the file
      else{
        $attributeValue = '<a href="'.$mediaUrl.'" target="_blank">'.$attributeValue.'</a>';
      }

    }

    // Return the modified template or only the resulted attribute value
    if($popupFeatureContent){
      // Replace {$mycol} by the processed column value
      $popupFeatureContent = preg_replace(
        '#\{\$'.$attributeName.'\}#i',
        $attributeValue,
        $popupFeatureContent
      );
      return $popupFeatureContent;
    }else{
      // Return the modified attributeValue
      return $attributeValue;
    }

  }



}