<?php

require_once(_PS_MODULE_DIR_.'/erply/Exception.php');
require_once(_PS_MODULE_DIR_.'/erply/Api/ErrorMessages.php');

class Utils
{
    /**
     * Helper displaying error message(s)
     * @param string|array $error
     * @return string
     */
    public static function displayError($error)
    {
        $output = '
        <div class="bootstrap">
        <div class="module_error alert alert-danger" >
            <button type="button" class="close" data-dismiss="alert">&times;</button>';

        if (is_array($error)) {
            $output .= '<ul>';
            foreach ($error as $msg) {
                $output .= '<li>'.$msg.'</li>';
            }
            $output .= '</ul>';
        } else {
            $output .= $error;
        }

        // Close div openned previously
        $output .= '</div></div>';

        return $output;
    }

    /**
    * Helper displaying warning message(s)
    * @param string|array $error
    * @return string
    */
    public static function displayWarning($warning)
    {
        $output = '
        <div class="bootstrap">
        <div class="module_warning alert alert-warning" >
            <button type="button" class="close" data-dismiss="alert">&times;</button>';

        if (is_array($warning)) {
            $output .= '<ul>';
            foreach ($warning as $msg) {
                $output .= '<li>'.$msg.'</li>';
            }
            $output .= '</ul>';
        } else {
            $output .= $warning;
        }

        // Close div openned previously
        $output .= '</div></div>';

        return $output;
    }

    public static function displayConfirmation($string)
    {
        $output = '
        <div class="bootstrap">
        <div class="module_confirmation conf confirm alert alert-success">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            '.$string.'
        </div>
        </div>';
        return $output;
    }
    
    public static function getErrorHtml(Erply_Exception $e) 
    {
        return self::displayError(self::getErrorText($e));
    }
    
    public static function getErrorText(Erply_Exception $e) 
    {
        $output = 'ERROR:<br>';
        $it = 0;
        $dbg_info = debug_backtrace();
        foreach(debug_backtrace() as $bt) {
            $file = $bt['file'];
            $line = $bt['line'];
            $func = $bt['function'];
            $output .= $file.': '.$func.' @'.$line.'<br>';
            if($it++ >= 5) {
                break;
            }
        }
        
        $apiResponseObj = $e->getData('apiResponseObj');
        $errorField = null;
        if(isset($apiResponseObj->getRawResponse()['status']['errorField'])) {
            $errorField = $apiResponseObj->getRawResponse()['status']['errorField'];
        }
        
        $output .= '<br>Message: '.$e->getMessage().
                    '<br>Code: '.$e->getCode().
                    ($errorField ? '<br>Error Field: ' . $errorField : '').
                    ($e->getData('output') ? '<br>Output: '.$e->getData('output') : '');
        
        return $output;
    }
    
    public static function returnOkHtml() 
    {
        return self::displayConfirmation('Success. '.debug_backtrace()[1]['function']);
    }
    
    public static function returnNotImplementedHtml($cause)
    {
        return self::displayWarning('This feature is not implemented or unavailable. Reason: '.$cause);
    }
}