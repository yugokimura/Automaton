<?php

class AutoLoader {

	private $dirs = array();

	public function __construct() {
		spl_autoload_register(array($this, 'loader'), true, true);
	}

	public function setIncludePath($path) {
		// もともとのパスを優先し新規を後ろに登録
		set_include_path(implode(PATH_SEPARATOR, array(
    			get_include_path(),
			$path,
		)));
	}
	
	public function loader($className) {
		$className = ltrim($className, '\\');

		if (false !== ($pos = strrpos($className, '\\'))) {
        		$namespace = substr($className, 0, $pos);
        		$className = substr($className, $pos + 1);
			$fileName = $className;
			
        		$fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace)
            			. DIRECTORY_SEPARATOR
            			. str_replace('_', DIRECTORY_SEPARATOR, $className);
		
    		} else {
       			 $fileName = str_replace('_', DIRECTORY_SEPARATOR, $className);
    		}

		// include_pathに含まれるディレクトリから検索、絶対パスを返却
		if ( false !== ($filePath = stream_resolve_include_path($fileName . '.php') ) )
			return include $filePath;

		return false;		
	}
}
?>
