<?php
namespace mnshankar\CSV;

class CSV
{
    protected $source;
    protected $handle;
    protected $header;
    protected $headerRowExists = true;
    protected $delimiter = ',';
    protected $enclosure = '"';
    protected $withSeparator = false;
    protected $ignored = [];

    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    public function setHeaderRowExists($headerFlag = true)
    {
        $this->headerRowExists = $headerFlag;

        return $this;
    }

    public function setEnclosure($enclosure)
    {
        $this->enclosure = $enclosure;

        return $this;
    }

    public function with($source, $headerRowExists = true, $mode = 'r+')
    {
        $this->ignored = [];

        if (is_array($source)) { // fromArray
            $this->source = $source;
        } else {
            if (is_string($source)) { // fromfile
                $this->fromFile($source, $headerRowExists, $mode);
            } else {
                throw new \Exception('Source must be either an array or a file name');
            }
        }

        return $this;
    }

    /**
     * Sets the $withSeparator property to true to specify the delimiter in the file
     * @return $this
     */
    public function withSeparator()
    {
        $this->withSeparator = true;
        return $this;
    }

    public function fromArray($arr)
    {
        $this->source = $arr;

        return $this;
    }

    public function toArray()
    {
        return $this->source;
    }

    public function fromFile($filePath, $headerRowExists = true, $mode = 'r+')
    {
        $from = fopen($filePath, $mode);
        $arr = array();
        $this->headerRowExists = $headerRowExists;
        $this->ignored = [];

        if ($headerRowExists) {
            // first header row
            $this->header = fgetcsv($from, 0, $this->delimiter, $this->enclosure);
        }
        while (($data = fgetcsv($from, 0, $this->delimiter, $this->enclosure)) !== false) {
            try {
                $arr[] = $this->getData($data);
            } catch (\Exception $e) {}
        }

        fclose($from);
        $this->source = $arr;

        return $this;
    }

    public function put($filePath, $mode = 'w+')
    {
        $fileToCreate = fopen($filePath, $mode);
        fwrite($fileToCreate, $this->toString());
        fclose($fileToCreate);
    }

    public function render($filename = 'export.csv', $mode = 'r+')
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private');
        header('pragma: cache');

        $response = $this->toString();

        if( $this->withSeparator ){
            $response = 'sep='.$this->delimiter.PHP_EOL.$response;
        }

        echo $response;
        exit;
    }

    public function getIgnored()
    {
        return $this->ignored;
    }

    /**
     * Get data read from a line
     * @param array $data
     * @return array
     */
    private function getData($data)
    {
        if ($this->headerRowExists) {
            if (count($this->header) != count($data)) {
                $this->ignored[] = $data;
                throw new \Exception();
            }
            return array_combine($this->header, $data);
        }

        return $data;
    }

    /**
     * Use PHP's inbuilt fputcsv to generate csv
     */
    private function getCSV()
    {
        $outputStream = fopen('php://output', 'r+');
        if ($this->headerRowExists) {
            $longest_row = max($this->source);
            $header = array_keys(static::dot($longest_row));
            fputcsv($outputStream, $header, $this->delimiter, $this->enclosure);
        }

        foreach ($this->source as $key => $row) {
            fputcsv($outputStream, static::dot($row), $this->delimiter, $this->enclosure);
        }
        fclose($outputStream);
    }

    //this method is used by unit tests. So it is public.
    public function toString()
    {
        ob_start(); // buffer the output ...

        $this->getCSV();

        return ob_get_clean(); //return it as a string
    }

    /**
     * Copied from illuminate array to avoid dependence on illuminate/support
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param  array $array
     * @param  string $prepend
     * @return array
     */
    public static function dot($array, $prepend = '')
    {
        $results = array();

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }
}
