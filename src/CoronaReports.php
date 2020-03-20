<?php
namespace SapiStudio\NovelCovid;
use SapiStudio\FileDatabase\Handler as FileDatabase;
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
    }
    
    /** Engine::loadLocalDb()*/
    public static function loadLocalDb()
    {
        return FileDatabase::load('coronareports',['fields'=>['coronaConfirmed'=>'integer','coronaDeaths'=>'integer','coronaRecovered'=>'integer','coronaCountry'=>'string','coronaDate'=>'string']]);
    }
    
    /** Engine::retrieveCountryReport()*/
    public static function retrieveCountryReport($countryName = null)
    {
        if(!$countryName)
            throw new \Exception('Invalid country name');
        return self::loadLocalDb()->query()->where('coronaCountry', '=', $countryName)->find()->toArray();
    }
    
    /** Engine::retrieveAllReports()*/
    public static function retrieveAllReports()
    {
        return self::loadLocalDb()->findAll()->toArray();
    }
    
    /** Engine::dailyReports()*/
    public static function dailyReports()
    {
        $corona = [];
        foreach(self::loadLocalDb()->findAll()->groupArray('coronaDate') as $dateIndex=>$dateValues){
            $corona[] = [
                'date'      => $dateIndex,
                'Confirmed' => array_sum(array_column($dateValues,'coronaConfirmed')),
                'Deaths'    => array_sum(array_column($dateValues,'coronaDeaths')),
                'Recovered' => array_sum(array_column($dateValues,'coronaRecovered'))
            ];
        }
        return $corona;
    }
    
    /** Engine::importReport()*/
    public static function importReport(){
        return (new self)->getAndInsertReports();
    }
    
    /** Engine::getAndInsertReports()*/
    private function getAndInsertReports()
    {
        self::loadLocalDb()->delete();
        foreach ([$this->confirmed_url, $this->deaths_url, $this->recovered_url] as $urlData) {
            $fileLogData = (new CsvParser(StreamClient::make()->getPageContent($urlData)))->firstRowHeader();
            foreach ($fileLogData->toArray() as $dataIndex => $dataLog) {
                $country = $dataLog['Country/Region'];
                unset($dataLog['Country/Region'], $dataLog['Province/State'], $dataLog['Lat'], $dataLog['Long']);
                switch ($urlData) {
                    case $this->confirmed_url:
                        $rowname = 'coronaConfirmed';
                        break;
                    case $this->deaths_url:
                        $rowname = 'coronaDeaths';
                        break;
                    case $this->recovered_url:
                        $rowname = 'coronaRecovered';
                        break;
                }
                foreach ($dataLog as $dataDate => $dataConfirmed) {
                    $return[$country][(new \DateTime($dataDate))->format('Y-m-d')][$rowname] += $dataConfirmed;
                }
            }
        }

        foreach ($return as $countryName => $countryData) {
            $last = null;
            foreach ($countryData as $countryDate => $countryInsert) {
                $countryInsert['coronaCountry'] = $countryName;
                $countryInsert['coronaDate'] = $countryDate;
                $original = $countryInsert;
                if (isset($last)) {
                    $countryInsert['coronaConfirmed'] = $countryInsert['coronaConfirmed'] - $last['coronaConfirmed'];
                    $countryInsert['coronaDeaths'] = $countryInsert['coronaDeaths'] - $last['coronaDeaths'];
                    $countryInsert['coronaRecovered'] = $countryInsert['coronaRecovered'] - $last['coronaRecovered'];
                }
                self::loadLocalDb()->addEntry($countryInsert);
                $last = $original;
            }
        }
        return $this;
    }
}
