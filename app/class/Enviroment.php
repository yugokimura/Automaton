<?php
class Enviroment {

        private $dev;
        private $stg;
        private $pro;

        function __construct() {

                $this->dev = false;
                $this->stg = false;
                $this->pro = false;

                // ${PROJECT_NAME}-${SYSTEM_VERSION}-${ENV}-${LAYER}-${SERVER_ENUM}
                if(preg_match_all('/\w+?-\d+?-(\w+?)-(\w+?)-\d{3}/', gethostname(), $matches, PREG_SET_ORDER)) {
                        switch($matches[0][1]) {
                        case 'pro' :
                                $this->pro = true;
                                break;
                        case 'stg' :
                                $this->stg = true;
                                break;
                        case 'dev' :
                                $this->dev = true;
                                break;
                        default :
                                break;
                        }
                }
        }

        public function getEnviroment() {
                if($this->pro === true )
                        return 'pro';
                else if($this->stg === true)
                        return 'stg';
                else if($this->dev === true)
                        return 'dev';
                else
                        return null;
        }

	public function is($enviroment) {
		if( $this->{$enviroment} == true ) {
			return true;
		} else {
			return false;
		}

	}

}
?>
