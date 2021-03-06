<?php
/**
 * Audio Player
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @author Sebastian Doell <sebastian@libasys.de>
 * @copyright 2016-2017 Marcel Scherello
 * @copyright 2015 Sebastian Doell
 */
 
namespace OCA\audioplayer\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\ILogger;

class PhotoController extends Controller {
	
	private $l10n;
	private $helperController;
    private $logger;
	public function __construct(
			$appName, 
			IRequest $request,
            ILogger $logger
    ) {
		parent::__construct($appName, $request);
        $this->logger = $logger;
	}
	
	/**
	 * @NoAdminRequired
	 */
	
	public function cropPhoto($id, $tmpkey){
		$params=array(
		 'tmpkey' => $tmpkey,
		 'id' => $id,
		);	
		$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
		$csp->addAllowedImageDomain('data:');
		
		$response = new TemplateResponse('audioplayer', 'part.cropphoto', $params, '');
	 	$response->setContentSecurityPolicy($csp);
	  
	   return $response;
	}
	
	/**
	 * @NoAdminRequired
	 */
	 
	public function clearPhotoCache($tmpkey){
		$data = \OC::$server->getCache()->get($tmpkey);
		if($data) {
			\OC::$server->getCache()->remove($tmpkey);
		}
	}
	
	/**
	 * @NoAdminRequired
	 */
	public function saveCropPhoto($id,$tmpkey, $x1, $y1, $w, $h){
		$x = $x1 ?: 0;	
		$y = $y1 ?: 0;	
		$w = $w ?: -1;	
		$h = $h ?: -1;	
		
		//\OCP\Util::writeLog('audioplayer','MIMI'.$tmpkey,\OCP\Util::DEBUG);	
		$data = \OC::$server->getCache()->get($tmpkey);
		if($data) {
			
			$image = new \OCP\Image();
			if($image->loadFromdata($data)) {
				$w = ($w !== -1 ? $w : $image->width());
				$h = ($h !== -1 ? $h : $image->height());
				
				if($image->crop($x, $y, $w, $h)) {
					if(($image->width() <= 300 && $image->height() <= 300) || $image->resize(300)) {
					
					$imgString = $image->__toString();
						
						$resultData=array(
							'id' => $id,
							'width' => $image->width(),
							'height' => $image->height(),
							'dataimg' =>$imgString,
							'mimetype' =>$image->mimeType()
						);
						
						 \OC::$server->getCache()->remove($tmpkey);
						 \OC::$server->getCache()->set($tmpkey, $image->data(), 600);
						 $response = new JSONResponse();
						 $response -> setData($resultData);
						  
						return $response;
					}
				}
			}
		}
		
		
	}
	
	/**
	 * @NoAdminRequired
	 */
	public function getImageFromCloud($id,$path){		
		$localpath = \OC\Files\Filesystem::getLocalFile($path);
		$tmpkey = 'audioplayer-photo-' . $id;
		$image = new \OCP\Image();
		$image -> loadFromFile($localpath);
		if ($image -> width() > 350 || $image -> height() > 350) {
			$image -> resize(350);
		}
		$image -> fixOrientation();
		
		$imgString = $image -> __toString();
		$imgMimeType = $image -> mimeType();
		if (\OC::$server->getCache()->set($tmpkey, $image -> data(), 600)) {
			
	    $resultData = array(
		     'id' =>$id,
		     'tmp' => $tmpkey,
		     'imgdata' => $imgString,
		     'mimetype' => $imgMimeType,
	      );
		  $response = new JSONResponse();
		  $response -> setData($resultData);
		return $response;
} 		
}
/**
	 * @NoAdminRequired
	 */
	public function uploadPhoto($id){
		$file = $this->request->getUploadedFile('imagefile');
		
		$error = $file['error'];
		if($error !== UPLOAD_ERR_OK) {
			$errors = array(
				0=>$this->l10n->t("There is no error, the file uploaded with success"),
				1=>$this->l10n->t("The uploaded file exceeds the upload_max_filesize directive in php.ini").ini_get('upload_max_filesize'),
				2=>$this->l10n->t("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form"),
				3=>$this->l10n->t("The uploaded file was only partially uploaded"),
				4=>$this->l10n->t("No file was uploaded"),
				6=>$this->l10n->t("Missing a temporary folder")
			);
            $this->logger->debug('Uploaderror: '.$errors[$error], array('app' => 'audioplayer'));
		}

		if(file_exists($file['tmp_name'])) {
			$tmpkey = 'audioplayer-photo-'.md5(basename($file['tmp_name']));
			$image = new \OCP\Image();
			if($image->loadFromFile($file['tmp_name'])) {
				
				if($image->width() > 350 || $image->height() > 350) {
					$image->resize(350); // Prettier resizing than with browser and saves bandwidth.
				}
				if(!$image->fixOrientation()) { // No fatal error so we don't bail out.
                    $this->logger->debug('Couldn\'t save correct image orientation: '.$tmpkey, array('app' => 'audioplayer'));
				}
					if(\OC::$server->getCache()->set($tmpkey, $image->data(), 600)) {
					$imgString=$image->__toString();
                      			$resultData=array(
							'mime'=>$file['type'],
							'size'=>$file['size'],
							'name'=>$file['name'],
							'id'=>$id,
							'tmp'=>$tmpkey,
							'imgdata' =>$imgString,
					);
					
					 $response = new JSONResponse();
					  $response -> setData($resultData);
					  
					return $response;
				}
			}
		}
	}
}
