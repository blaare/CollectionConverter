<?php
/**
 * Class    CollectionConverter
 * Goal:    The sole responsibility of this class is to put a collection in a data file.
 *
 * For Models:
 *          Specify the collection containing the
 *
 */

class CollectionConverter
{

    private $delimiter;
    private $enclosure;
    private $fileExtension;
    private $filename;
    private $escape;
    private static $xmlFormat;
    //Options for makeFromPreset
    const TSV = "TSV";
    const CSV = "CSV";
    const PSV = "PSV";
    const XML = "XML";

    /**
     * CollectionConverter constructor.
     * -----------------------------------------------------------------------------|
     * Usage:   $CC = new CollectionConverter('testing',"\t", "'", '.tsv', '\\');   |
     * -----------------------------------------------------------------------------|
     * @param string $delimiter
     * @param string $enclosure
     * @param string $fileExtension
     * @param string $filename - full file path
     * @param string $escape
     */
    public function __construct( $filename, $delimiter = ",",$enclosure = '"', $fileExtension = '.csv', $escape = "\n"){
        $this->delimiter        = $delimiter;
        $this->enclosure        = $enclosure;
        $this->fileExtension    = $fileExtension;
        $this->escape           = $escape;
        $this->filename         = $filename;
        $this->checkForDuplicateOptions();
    }

    /**
     * Function makePreset
     * Goal:    to allow quick generation of CollectionConverter Classes based on
     *          presets
     *
     * -----------------------------------------------------------------------------------------|
     * Usage:   $CC = CollectionConverter::makeFromPreset(CollectionConverter::CSV, "testing"); |
     * -----------------------------------------------------------------------------------------|
     *
     * @param $option
     * @param $filename
     * @return bool|CollectionConverter
     */
    public static function makeFromPreset($option, $filename){
        switch($option){
            case self::TSV: {
                self::$xmlFormat = false;
                return new self($filename, "\t", '"', '.tsv', "\n");
            }
            case self::CSV: {
                self::$xmlFormat  = false;
                return new self($filename, ",", '"', '.csv', "\n");
            }
            case self::PSV: {
                self::$xmlFormat  = false;
                return new self($filename, "|", '"', '.psv',"\n");
            }
            case self::XML: {
                self::$xmlFormat  = true;
                return new self($filename, "XML", "<>", '.xml', "\r");
            }
            default:
                return false;
        }
    }

    /**
     * Function exportModelCollection
     * Goal     to create a CSV file, data extracted from given collection, served as a wrapper method for
     *          writeSVFile and writeXMLFile
     * ---------------------------------------------------------------------------------------------|
     * Usage:   $Models  = ModelClass::all();                                                       |
     *          $CC      = CollectionConverter::makeFromPreset(CollectionConverter::CSV, "testing");|
     *          -or-                                                                                |
     *          $CC      = CollectionConverter::makeFromPreset(CollectionConverter::XML, "testing");|
     *          $CC->exportFormattedModelCollection($Models);                                       |
     * ---------------------------------------------------------------------------------------------|
     *
     * @param \Illuminate\Database\Eloquent\Collection $ModelCollection
     * @return boolean | string
     */
    public function exportFormattedModelCollection(\Illuminate\Database\Eloquent\Collection $ModelCollection){
        if(self::$xmlFormat )
            return $this->writeXMLFile($ModelCollection);
        else
            return $this->writeSVFile($ModelCollection);
    }

    /**
     * getFullFileName
     *
     * Returns the combined filename and
     * the specified extension.
     * i.e. <filename>.csv
     * where ".csv" is fileExtension
     *
     * @return string
     */
    private function getFullFileName(){
        return $this->filename.$this->fileExtension;
    }

    /**
     * Function writeSVFile
     * Goal     To create a SeparatedValue File, ex: CSV, PSV, TSV,
     *          This method is accessed by exportFormattedModelCollection
     * @param \Illuminate\Database\Eloquent\Collection $ModelCollection
     * @return bool|string
     */
    private function writeSVFile(\Illuminate\Database\Eloquent\Collection $ModelCollection){
        $file   = fopen($this->getFullFileName(), "w");
        //Get the Column Headers
        $Model  = $ModelCollection->first();
        $temp   = $this->getColumnHeaders($Model);
        //Get Model in string form
        $temp   = $this->implodeArray($temp);
        //Write TO Array
        fwrite($file,$temp);
        //Include row separator (escape)
        fwrite($file,$this->escape);
        //Loop through each model in the collection, get values in string form, write to file
        foreach($ModelCollection as $Model){
            if($Model instanceof \Illuminate\Database\Eloquent\Model){
                $temp   = $this->getColumnValues($Model);
                $temp   = $this->implodeArray($temp);
                fwrite($file,$temp);
                fwrite($file,$this->escape);
            }
            else {
                fclose($file);
                unlink($this->getFullFileName());
                return false;
            }
        }
        fclose($file);
        return $this->getFullFileName();
    }

    /**
     * Function writeXMLFile
     * Goal     to write an XMLFile, primarily used in exportFormattedModelCollection.
     * @param \Illuminate\Database\Eloquent\Collection $ModelCollection
     * @return bool
     */
    private function writeXMLFile(\Illuminate\Database\Eloquent\Collection $ModelCollection){
        $file           = fopen($this->getFullFileName(), "w");
        fwrite($file,"<?xml version=\"1.0\"?>");
        $CollectionName = new ReflectionClass($ModelCollection);
        fwrite($file,"<".get_class($ModelCollection->first()).$CollectionName->getShortName().">");
        //Loop through each model in the collection, get values in string form, write to file
        foreach($ModelCollection as $Model){
            if($Model instanceof \Illuminate\Database\Eloquent\Model){
                fwrite($file,"<".get_class($Model).">");
                $temp = $this->implodeArrayToXML($Model->getAttributes());
                fwrite($file,$temp);
                fwrite($file,"</".get_class($Model).">");
            }
            else {
                fclose($file);
                unlink($this->getFullFileName());
                return false;
            }
        }
        fwrite($file,"</".get_class($ModelCollection->first()).$CollectionName->getShortName().">");
        fclose($file);
        return self::beautifyXML($this->getFullFileName());
    }

    /**
     * Function getColumnValues
     * Goal:    to return an array of attributes of the specified model
     *
     * -------------------------------------------------------------------------------------------------|
     * Usage:   $model      = $ModelClass::all()->first();                                              |
     *          $CC         = CollectionConverter::makeFromPreset(CollectionConverter::CSV, "testing"); |
     *          $values     = $CC->getColumnValues($model);                                             |
     * -------------------------------------------------------------------------------------------------|
     *
     * @param \Illuminate\Database\Eloquent\Model $Model
     * @return array
     */
    public function getColumnValues(\Illuminate\Database\Eloquent\Model $Model){
        return array_values($Model->getAttributes());
    }

    /**
     * Function getColumnHeaders
     * Goal:    to get the column headers from a model.
     * ---------------------------------------------------------------------------------------------|
     * Usage:   $models = models::all()->first();                                                 |
     *          $CC     = CollectionConverter::makeFromPreset(CollectionConverter::CSV, "testing"); |
     *          $values = $CC->getColumnHeaders($models);                                          |
     * ---------------------------------------------------------------------------------------------|
     * @param \Illuminate\Database\Eloquent\Model $Model
     * @return array
     */
    public function getColumnHeaders(\Illuminate\Database\Eloquent\Model $Model){
        return array_keys($Model->getAttributes());
    }

    /**
     * Function implodeArray
     * Goal     to allow imploding of an array with set delimiter and enclosure
     * ---------------------------------------------------------------------------------------------|
     * Usage:   $models= models::all()->first();                                                  |
     *          $CC     = CollectionConverter::makeFromPreset(CollectionConverter::CSV, "testing"); |
     *          $values = $CC->getColumnValues($models);                                           |
     *          $string = $CC->implodeArray($models);                                              |
     * ---------------------------------------------------------------------------------------------|
     * @param $arrayValues
     * @return string
     */
    public function implodeArray($arrayValues){
        $output = "";
        for($i  = 0; $i < count($arrayValues); $i++){
            $output .= $this->enclosure.addcslashes($arrayValues[$i],'"\\/').$this->enclosure;
            if($i   != count($arrayValues)-1)
                $output .= $this->delimiter;
        }
        return $output;
    }

    /**
     * Function implodeArrayToXML
     * Goal:    To enclose the array values with xml formatted key
     * ---------------------------------------------------------------------------------------------|
     * Usage:   $models= models::all()->first();                                                  |
     *          $CC     = CollectionConverter::makeFromPreset(CollectionConverter::CSV, "testing"); |
     *          $values = $CC->getColumnValues($models);                                           |
     *          $string = $CC->implodeArray($models;                                               |
     * ---------------------------------------------------------------------------------------------|
     * @param $array
     * @return string
     */
    public function implodeArrayToXML($array){
        $output = "";
        foreach($array as $key => $value){
            $output .= '<'. $key . '>';
            $output .= htmlspecialchars($value);
            $output .= '</'. $key . '>';
        }
        return $output;
    }

    /**
     * Function beautifyXML
     * Goal     to beautifyXML
     * -----------------------------------------------------|
     * Usage:   CollectionConverter::beautifyXML($filename) |
     * -----------------------------------------------------|
     *          $grossXmlFile
     * @param $filename
     * @return bool
     */
    public static function beautifyXML($filename){
        if(!realpath($filename))
            return false;
        $xml                = simplexml_load_file($filename);
        $sxe                = new SimpleXMLElement($xml->asXML());
        $dom                = dom_import_simplexml($sxe)->ownerDocument;
        $dom->formatOutput  = TRUE;
        $dom->save($filename);
        return $filename;
    }

    /**
     * Function changePreset
     * Goal     to allow on-the-fly preset changes
     * ---------------------------------------------------------------------|
     * Usage    $CC = makeFromPreset(CollectionConverter::XML, "testing");  |
     *          $CC->changePreset(CollectionConverter::CSV);                |
     * ---------------------------------------------------------------------|
     * @param $option
     * @param null $filename
     * @return bool
     */
    public function changePreset($option, $filename = null){
        switch($option){
            case self::TSV: {
                self::$xmlFormat = false;
                $this->changeFormat($filename, "\t", '"', '.tsv', "\n");
                return true;
            }
            case self::CSV: {
                self::$xmlFormat  = false;
                $this->changeFormat($filename, ",", '"', '.csv', "\n");
                return true;
            }
            case self::PSV: {
                self::$xmlFormat  = false;
                $this->changeFormat($filename, "|", '"', '.psv', "\n");
                return true;
            }
            case self::XML: {
                self::$xmlFormat  = true;
                $this->changeFormat($filename, null, null, '.xml', "\n");
                return true;
            }
            default:
                return false;
        }
    }

    /**
     * Function changeFormat
     * Goal     To allow changes on the fly to the previously defined format
     * -----------------------------------------------------------------------------------------|
     * Usage    $CC = CollectionConverter::makeFromPreset(CollectionConverter::TSV, "testing"); |
     *          $CC->changeFormat(null, null, "'", null, null);                                 |
     * -----------------------------------------------------------------------------------------|
     *
     * @param null $filename
     * @param string $delimiter
     * @param string $enclosure
     * @param string $fileExtension
     * @param string $escape
     */
    public function changeFormat($filename = null, $delimiter = null,$enclosure = null, $fileExtension = null, $escape = null){
        if(!is_null($filename))
            $this->filename         = $filename;
        if(!is_null($delimiter))
            $this->delimiter        = $delimiter;
        if(!is_null($enclosure))
            $this->enclosure        = $enclosure;
        if(!is_null($fileExtension))
            $this->fileExtension    = $fileExtension;
        if(!is_null($escape))
            $this->escape           = $escape;
        $this->checkForDuplicateOptions();

    }

    /**
     * Function checkForDuplicateOptions
     * Goal:    Used to check for duplicate options that could cause issues for Decoding/encoding, etc.
     * @throws DuplicateOptionException
     */
    private function checkForDuplicateOptions(){
        if( (!(bool)strcmp($this->escape,   $this->enclosure)) ||
            (!(bool)strcmp($this->escape,   $this->delimiter)) ||
            (!(bool)strcmp($this->delimiter,$this->enclosure)))
            throw new DuplicateOptionException();
    }

    /**
     * Generate a feed data file from an OutGoingFedTemplate
     * and a collection of cars.
     *
     * @param OutGoingFeedTemplate                     $template
     * @param \Illuminate\Database\Eloquent\Collection $cars
     * @return bool|string
     */
    public static function generateFeedDataFile(OutGoingFeedTemplate $template, \Illuminate\Database\Eloquent\Collection $cars){
        $dataType = $template->getDataType();
        if($dataType == 'xml')
            self::$xmlFormat = true;
        else
            self::$xmlFormat = false;

        $converter = new self($template->getFileName(),
                              $template->getDelimiter(),
                              $template->getEnclosure(),
                              $dataType,
                              $template->getEscape());

        return $converter->exportFormattedModelCollection($cars);
    }
}
