<?php
namespace SapiStudio\NovelCovid;
use SapiStudio\FileDatabase\Handler as FileDatabase;

class Engine
{

    protected $confirmed_url;
    protected $deaths_url;
    protected $recovered_url;
    protected $mainPathUrl;
    protected $startPandemicDate = '2020-01-22';
    
    public function __construct()
    {
        $this->mainPathUrl      = 'https://raw.githubusercontent.com/CSSEGISandData/COVID-19/master/csse_covid_19_data/csse_covid_19_time_series/';
        $this->archivePathUrl   = 'https://raw.githubusercontent.com/CSSEGISandData/COVID-19/master/csse_covid_19_data/csse_covid_19_daily_reports/';
        $this->confirmed_url    = $this->mainPathUrl . 'time_series_19-covid-Confirmed.csv';
        $this->deaths_url       = $this->mainPathUrl . 'time_series_19-covid-Deaths.csv';
        $this->recovered_url    = $this->mainPathUrl . 'time_series_19-covid-Recovered.csv';
    }
    
    public static function loadLocalDb()
    {
        return FileDatabase::load('coronareports', ['fields' => ['seo_url' => 'string']]);
    }
    
    /** Database::retrieveNews()*/
    public static function retrieveNews($totalPicker = 5)
    {
        return json_decode(json_encode(self::getNewsDb()->randomPick($totalPicker)),true);
    }
    
    /** Database::importNews()*/
    public static function importReport($report = []){
        return self::loadLocalDb()->addEntry($data);
    }
    
    public function getData()
    {
        foreach ([$this->confirmed_url, $this->deaths_url, $this->recovered_url] as $urlData) {
            $fileLogData = (new \SapiStudio\FileSystem\Parsers\CsvParser(\SapiStudio\Http\Browser\StreamClient::make()->getPageContent($urlData)))->firstRowHeader();
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
                $org = $countryInsert;
                if (isset($last)) {
                    $countryInsert['coronaConfirmed'] = $countryInsert['coronaConfirmed'] - $last['coronaConfirmed'];
                    $countryInsert['coronaDeaths'] = $countryInsert['coronaDeaths'] - $last['coronaDeaths'];
                    $countryInsert['coronaRecovered'] = $countryInsert['coronaRecovered'] - $last['coronaRecovered'];
                }
                self::importReport($countryInsert);
                $last = $org;
            }
        }
    }

    public function retrieveArchive()
    {
        $all = 0;
        $period = new \DatePeriod(new \DateTime($this->startPandemicDate), new \DateInterval
            ('P1D'), new \DateTime());
        foreach ($period as $key => $value) {
            $return[] = $this->archivePathUrl . $value->format('m-d-Y') . '.csv';
            $contentCsv = \SapiStudio\Http\Browser\StreamClient::make()->getPageContent($this->
                archivePathUrl . $value->format('m-d-Y') . '.csv');
            $fileLogData = (new \SapiStudio\FileSystem\Parsers\CsvParser($contentCsv))->
                firstRowHeader();
            foreach ($fileLogData->toArray() as $dataIndex => $dataLog) {

                $lastName = strtolower(str_replace(" ", "", $dataLog['CountryRegion'] . $dataLog['ProvinceState']));
                $confirmed = $dataLog['Confirmed'] - (int)$lastLog[$lastName]['Confirmed'];
                $deaths = $dataLog['Deaths'] - (int)$lastLog[$lastName]['Deaths'];
                $recovered = $dataLog['Recovered'] - (int)$lastLog[$lastName]['Recovered'];
                if (isset($dataLog['CountryRegion']) && $dataLog['CountryRegion'] != 'Mainland China') {
                    $dsadas = ['coronaCountry' => $dataLog['CountryRegion'], 'coronaState' => $dataLog['ProvinceState'],
                        'coronaConfirmed' => $confirmed, 'coronaDeaths' => $deaths, 'coronaRecovered' =>
                        $recovered, 'coronaLastUpdate' => $dataLog['LastUpdate'], 'coronaDate' => $value->
                        format('Y-m-d')];
                    \CoronaMapper::make()->add($dsadas);
                    $lastLog[$lastName] = $dataLog;
                }
            }
            $total = count($fileLogData->toArray());
            $all += $total;
        }
        return $return;
    }
}