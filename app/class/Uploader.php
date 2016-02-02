<?php

/**
 * uploadクラス
 * @example

$uploader = new Uploader();
$uploader->setTmpDir('/var/www/html/sample/app/tmp');
$uploader->setWriteDir('/var/www/html/sample/app/upload');
$uploader->setMinSize('10');
$uploader->setMaxSize('1000000');
$file = $uploader->prepare('upfile');
print_r($file);
$uploader->execute();
//$uploader->setNewName(mt_rand(10,200));
if( $r = $uploader->commit() ) {

        print "アップロード成功";

}
**/

class Uploader {

	protected $tmp_dir;
	protected $write_dir;

	protected $tmp_path;
	protected $tmp_name;
	protected $write_path;

	protected $file;
	protected $file_fullname;
	protected $file_name;
	protected $file_ext;
	protected $file_size;

	protected $min_size;
	protected $max_size;

	protected $is_image;
	protected $is_force; // 強制アップロード設定が必要

        function __construct() {
		$this->is_image = false;
		$this->is_force = false;
	}

	function __destruct() {

	}

	public function setTmpDir( $tmp_dir ) {
		$this->tmp_dir = $tmp_dir;
	}

	public function setWriteDir( $write_dir ) {
		$this->write_dir = $write_dir;
	}

	public function setNewName( $new_file_name ) {

		if( empty( $this->file_ext ) and empty( $new_file_name ) ) 
			$this->write_name = 'null_name_ ' . date('Ymd_His') . '_' . $this->file_size . '_' . mt_rand(10000000, 99999999);
		else if( empty( $this->file_ext ) and !empty( $new_file_name ) )
			$this->write_name = $new_file_name;
		else if( !empty( $this->file_ext ) and empty( $new_file_name ) )
			$this->write_name = '.' . $this->file_ext;
		else
			$this->write_name = $new_file_name . '.' . $this->file_ext;
	}

	public function setMinSize( $min ) {
		$this->min_size = $min;
	}

	public function setMaxSize( $max ) {
		$this->max_size = $max;
	}

	public function prepare( $file ) {

		$response = new stdClass();

		try {

			if( !isset( $this->tmp_dir ) )
				throw new RuntimeException('ファイル一時アップロードディレクトリ未指定', 400);

			if( !isset( $this->write_dir ) )
				throw new RuntimeException('ファイルアップロードディレクトリ未指定', 400);

			switch ($_FILES[$file]['error']) {
				case UPLOAD_ERR_OK: // OK
				break;
				case UPLOAD_ERR_NO_FILE:   // ファイル未選択
					throw new RuntimeException('ファイル未選択', 400);
				case UPLOAD_ERR_INI_SIZE:  // php.ini定義の最大サイズ超過
				case UPLOAD_ERR_FORM_SIZE: // フォーム定義の最大サイズ超過
					throw new RuntimeException('ファイルサイズ超過', 400);
				default:
					throw new RuntimeException('その他エラー', 400);
			}

			// ファイルサイズ確認
			if( isset( $this->min_size ) ) {
				if( $_FILES[$file]['size'] < $this->min_size ) {
					throw new RuntimeException('ファイルサイズ不足', 400);
				}
			}

			if( isset( $this->max_size ) ) {
				if( $_FILES[$file]['size']  > $this->max_size ) {
					throw new RuntimeException('ファイルサイズ超過', 400);
				}
			}

			if( $_FILES[$file]['name'] == '' or $_FILES[$file]['name'] == null)
				throw new RuntimeException('ファイル名が空', 400);

			// 画像かの確認
			$this->is_image = true;
			if (!$info = @getimagesize($_FILES[$file]['tmp_name'])) {
				$this->is_image = false;
            		}
            		if (!in_array($info[2], [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
				$this->is_image = false;
            		}
			if( $this->is_image == true ) {
				$response->image = new stdClass();
				$response->image->width  = $info[0];
				$response->image->height = $info[1];
			}

			$this->file = $_FILES[$file]['tmp_name'];
			$response->file_fullname = $this->file_fullname = mb_convert_encoding( $_FILES[$file]['name'], 'utf8' );
			$response->file_size     = $this->file_size = $_FILES[$file]['size'];

			if( strrpos($this->file_fullname, '.') == null ) {
				$response->file_ext = $this->file_ext = '';
				if( strlen( $this->file_fullname ) > 0)
					$response->file_name = $this->file_name = $response->file_fullname;
				else 
					$response->file_name = $this->file_name = '';
			} else {
				$response->file_ext  = $this->file_ext = substr($this->file_fullname, strrpos($this->file_fullname, '.') + 1);
				$response->file_name = $this->file_name = substr($this->file_fullname, 0 ,strrpos($this->file_fullname, '.') );
			}

			// .から始まるconfigファイルか確認
			if( preg_match('/^[\.]/', $this->file_name) ) {
				$response->is_force = $this->is_force = true;
			}

			// ファイル名を設定
			$this->setNewName( $this->file_name );

			return $response;

		} catch ( RuntimeException $e) {
			return false;
		}
	}

	public function execute( $is_force = false ) {

		$response = new stdClass();
		try {

			if( $this->is_force == true)
				if( $is_force == false )
					throw new RuntimeException('コンフィグファイル強制アップロードフラグが必要', 500);

			// アップロード処理
			$this->tmp_name = date('Ymd_His') . '_' . $this->file_size . '_' . mt_rand(10000000, 99999999);
			$this->tmp_path = $this->tmp_dir . '/' . $this->tmp_name;
			if( move_uploaded_file( $this->file, $this->tmp_path ) ) {
			} else {
				throw new RuntimeException('アップロードエラー', 500);
			}

		} catch ( RuntimeException $e) {
			$this->rollBack();
			return false;
		}

		return true;
	}

	public function commit() {

		$response = new stdClass();
		try {
			// 一時領域から本領域に移動
			if( file_exists ( $this->tmp_path ) ) {
				$this->write_path = $this->write_dir . '/' . $this->write_name;
				rename( $this->tmp_path, $this->write_path);
				if( file_exists ( $this->write_path ) ) {
					$response->write_path = $this->write_path;
					$response->write_name = $this->write_name;
				} else {
					throw new RuntimeException('ファイル移動エラー', 500);
				}
			} else {
				throw new RuntimeException('ファイル存在なし', 500);
			}

		} catch ( RuntimeException $e) {
			$this->rollBack();
                        return false;
                }
		return $response;
	}

	public function rollBack() {

		if( isset( $this->tmp_path ) ) {
			if( file_exists( $this->tmp_path ) ) {
				unlink( $this->tmp_path );
			}
		}

		if( isset( $this->write_path ) ) {
			if( file_exists( $this->write_path ) ) {
				unlink( $this->write_path );
			}
		}
	}

}
