<?php
namespace SapiStudio\NovelCovid;
use SapiStudio\FileSystem\Handler as FileSystem;
use SapiStudio\FileSystem\Parsers\CsvParser;
use \SapiStudio\Http\Browser\StreamClient;

class Engine
{
    protected $confirmed_url;
    protected $deaths_url;
    protected $recovered_url;
    protected $mainPathUrl;
    
    /** Engine::__construct()*/
    public function __construct()
    {
        $this->mainPathUrl      = 'https://raw.githubusercontent.com/CSSEGISandData/COVID-19/master/csse_covid_19_data/csse_covid_19_time_series/';
        $this->archivePathUrl   = 'https://raw.githubusercontent.com/CSSEGISandData/COVID-19/master/csse_covid_19_data/csse_covid_19_daily_reports/';
        $this->confirmed_url    = $this->mainPathUrl . 'time_series_19-covid-Confirmed.csv';
        $this->deaths_url       = $this->mainPathUrl . 'time_series_19-covid-Deaths.csv';
        $this->recovered_url    = $this->mainPathUrl . 'time_series_19-covid-Recovered.csv';
        $this->setDir();
    }
    
    /** Engine::setDir()*/
    public function setDir(){
        $dir = realpath(__DIR__).DIRECTORY_SEPARATOR.'json'.DIRECTORY_SEPARATOR;
        if(!is_dir($dir))
            mkdir($dir,0755,true);
        $this->filePathJson = $dir.'coronareport.json';        
        return $this;
    }
    
    /** Engine::loadJson()*/
    public function loadJson()
    {
        return FileSystem::loadJson($this->filePathJson);
    }
    
    /** Engine::saveJson()*/
    public function saveJson($jsonData)
    {
        return FileSystem::dumpJson($this->filePathJson,$jsonData);
    }
    
    /** Engine::countryReport()*/
    public static function countryReport($countryName = '')
    {
        $stat = (new self)->loadJson()->countries;
        return (isset($stat->$countryName)) ? $stat->$countryName : $stat;
    }
    
    /** Engine::chartReports()*/
    public static function chartReports($country = 'all')
    {
        return (new self)->loadJson()->chart->$country;
    }
    
    /** Engine::importReport()*/
    public static function importReport(){
        return (new self)->getAndInsertReports();
    }
    
    /** Engine::getAndInsertReports()*/
    private function getAndInsertReports()
    {
        $indexRow = 0;
        foreach (json_decode(StreamClient::make()->getPageContent('https://corona.lmao.ninja/v2/historical'),true) as $historicalData) {
            foreach ($historicalData['timeline'] as $dataType => $dataLog) {
                foreach($dataLog as $dataDate => $dataValue){
                    switch ($dataType) {
                        case 'cases':
                            $rowname = 'coronaConfirmed';
                            break;
                        case 'deaths':
                            $rowname = 'coronaDeaths';
                            break;
                        case 'recovered':
                            $rowname = 'coronaRecovered';
                            break;
                    }
                    $return[$indexRow][(new \DateTime($dataDate))->format('2020-m-d')]['coronaCountry'] = strtolower($historicalData['country']);
                    $return[$indexRow][(new \DateTime($dataDate))->format('2020-m-d')][$rowname] += $dataValue;
                }
            }
            $indexRow++;   
        }
        foreach ($return as $returnIndex => $countryData) {
            $last = null;
            foreach ($countryData as $countryDate => $countryInsert) {
                $countryInsert['coronaDate'] = $countryDate;
                $original = $countryInsert;
                if (isset($last)) {
                    $countryInsert['coronaConfirmed']   = $countryInsert['coronaConfirmed'] - $last['coronaConfirmed'];
                    $countryInsert['coronaDeaths']      = $countryInsert['coronaDeaths'] - $last['coronaDeaths'];
                    $countryInsert['coronaRecovered']   = $countryInsert['coronaRecovered'] - $last['coronaRecovered'];
                }
                $parsed[]   = $countryInsert;
                foreach(['all',$countryInsert['coronaCountry']] as $type){
                    $coronaChart[$type][$countryDate]['date'] =$countryDate;
                    $coronaChart[$type][$countryDate]['Confirmed'] +=$countryInsert['coronaConfirmed'];
                    $coronaChart[$type][$countryDate]['Deaths'] +=$countryInsert['coronaDeaths'];
                    $coronaChart[$type][$countryDate]['Recovered'] +=$countryInsert['coronaRecovered'];
                }
                $last = $original;
            }
        }
        foreach(json_decode(StreamClient::make()->getPageContent('https://corona.lmao.ninja/countries')) as $coronaIndex => $coronaData){
            $coronaStat[strtolower($coronaData->country)] = $coronaData;
        }
        $this->saveJson(['data'=>$parsed,'chart'=>$coronaChart,'countries' => $coronaStat]);
        return $this;
    }
}
