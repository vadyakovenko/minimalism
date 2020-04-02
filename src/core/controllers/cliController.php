<?php
namespace carlonicora\minimalism\core\controllers;

use carlonicora\minimalism\core\bootstrapper;
use carlonicora\minimalism\core\controllers\abstracts\abstractController;

class cliController extends abstractController {
    /**
     * @return string
     */
    public function render(): string {
        $error = $this->model->preRender();
        if ($error !== null){
            return $error->toJson();
        }

        $this->model->run();

        if (bootstrapper::$servicesCache !== null){
            file_put_contents(bootstrapper::$servicesCache, serialize($this->services));
        }

        return '';
    }

    /**
     * @param array $parameterValueList
     * @param array $parameterValues
     */
    protected function initialiseParameters(array $parameterValueList, array $parameterValues): void {
        if (isset($_SERVER['argv'][1]) && !isset($_SERVER['argv'][2])){
            $this->passedParameters = json_decode($_SERVER['argv'][1], true, 512, JSON_THROW_ON_ERROR);
        } else if (count($_SERVER['argv']) > 1){
            for ($argumentCount = 1, $argumentCountMax = count($_SERVER['argv']); $argumentCount < $argumentCountMax; $argumentCount += 2){
                $this->passedParameters[substr($_SERVER['argv'][$argumentCount], 1)] = $_SERVER['argv'][$argumentCount + 1];
            }
        }
    }
}