<?php
/**
 * PHPReport
 * Library for generating reports from PHP
 * Copyright (c) 2012 PHPReport
 * 
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 *
 * @package PHPReport
 * @author Vernes Šiljegović
 * @copyright  Copyright (c) 2012 PHPReport
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt	LGPL
 * @version 1.0, 2012-03-04
 */

/**
 * PHPExcel
 *
 * @copyright  Copyright (c) 2006 - 2011 PHPExcel (http://www.codeplex.com/PHPExcel)
 */
require_once 'PHPExcel.php';

class PHPReport {
    
    //report template
    private $_templateDir;
    private $_template;
    
    //internal collections of data
    private $_data=array();
    private $_search=array();
    private $_replace=array();
    private $_group=array();
    
    //parameters
    private $_renderHeading=false;
    private $_useStripRows=false;
    private $_headingText;
    private $_noResultText;
    
    //styling
    private $headerStyleArray = array(
				'font' => array(
					'bold' => true,
					'color' => array(
						'rgb' => 'FFFFFF'
						)
				),
				'fill' => array(
					'type' => PHPExcel_Style_Fill::FILL_SOLID,
					'startcolor' => array(
						'rgb' => '4E5A7A'
						)
					)
			);
	private $footerStyleArray = array(
				'font' => array(
					'bold' => true,
				),
				'fill' => array(
					'type' => PHPExcel_Style_Fill::FILL_SOLID,
					'startcolor' => array(
						'rgb' => 'E4E8F3',
						)
					)
				);
	private $headerGroupStyleArray = array(
					'alignment' => array(
						'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT
					),
					'font' => array(
						'bold' => true
					),
					'fill' => array(
						'type' => PHPExcel_Style_Fill::FILL_SOLID,
						'startcolor' => array(
							'rgb' => '8DB4E3'
							)
						)
				);
	private $footerGroupStyleArray = array(
					'font' => array(
						'bold' => true
					),
					'fill' => array(
						'type' => PHPExcel_Style_Fill::FILL_SOLID,
						'startcolor' => array(
							'rgb' => 'C5D9F1'
							)
						)
				);
    private $noResultStyleArray = array(
					'borders' => array(
						'outline' => array(
							'style' => PHPExcel_Style_Border::BORDER_THIN
						)
					),
					'alignment' => array(
						'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
					),
					'font' => array(
						'bold' => true
					),
					'fill' => array(
						'type' => PHPExcel_Style_Fill::FILL_SOLID,
						'startcolor' => array(
							'rgb' => 'FFEBA5'
							)
						)
				);
    private $headingStyleArray = array(
				'font' => array(
					'bold' => true,
					'color' => array(
						'rgb' => '4E5A7A'
						),
					'size' => '24'
				),
                'alignment' => array(
                    'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
                )
			);
    
    //PHPExcel objects
	private $objReader;
	private $objPHPExcel;
	private $objWorksheet;
	private $objWriter;
    
    /**
     * Creates new report with some configuration parameters
     * @param array $config 
     */
    public function __construct($config=array())
	{
        $this->setConfig($config);
        $this->init();
	}
    
    /**
     * Uses configuration array to adjust report parameters
     * @param array $config 
     */
    public function setConfig($config)
    {
        if(!is_array($config))
            die('Unable to use non-array configuration');
        
        foreach($config as $key=>$value)
        {
            $this->$key=$value;
        }
    }
    
    /**
     * Initializes internal objects 
     */
    private function init()
    {
        if(is_file($this->_templateDir.$this->_template))
        {
            $this->loadTemplate();
        }
        else
        {
            $this->createTemplate();
        }
    }
    
    /**
	 * Loads Excel file as a template for report
	 */
	public function loadTemplate($template='')
	{
		if($template!='' && is_file($this->_templateDir.$template))
			$this->_template=$template;
		
		//identify type of template file
		$inputFileType = PHPExcel_IOFactory::identify($this->_templateDir.$this->_template);
		//TODO: better control of allowed input types
		//load template file into PHPExcel objects
		$this->objReader = PHPExcel_IOFactory::createReader($inputFileType);
		$this->objPHPExcel = $this->objReader->load($this->_templateDir.$this->_template);
		$this->objWorksheet = $this->objPHPExcel->getActiveSheet();
	}
	
	/**
	 * Creates PHPExcel object and template for report
	 */
	private function createTemplate()
	{
		$this->objPHPExcel = new PHPExcel();
		$this->objPHPExcel->setActiveSheetIndex(0);
		$this->objWorksheet = $this->objPHPExcel->getActiveSheet();
	}
    
	/**
	 * Takes an array of all the data for report
	 * 
	 * @param array $data Associative array with two elements
	 * id - unique identifier of data group
	 * data - Single array of data
	 */
	public function load($data)
	{
		if(!is_array($data))
			die("Could not load a non-array data!");
		if(!isset ($data['id']))
			die("Every array of data needs an 'id'!");
		if(!isset ($data['data']))
			die("Loaded array needs an element 'data'!");
		
		$this->_data[]=$data;
	}
    
    /**
     * Generates report based on loaded data 
     */
    public function generateReport()
    {
        foreach($this->_data as $data)
		{
			if(isset ($data['repeat']) && $data['repeat']==true)
			{
				//Repeating data
				$foundTags=false;
				$repeatRange='';
				$firstRow='';
				$lastRow='';
				
				$firstCol='A';//TODO: better detection
				$lastCol=$this->objWorksheet->getHighestColumn();//TODO: better detection
				
				//scan the template
				//search for repeating part
				foreach ($this->objWorksheet->getRowIterator() as $row)
				{
					$cellIterator = $row->getCellIterator();
					$rowIndex = $row->getRowIndex();
					//find the repeating range (one or more rows)
					foreach ($cellIterator as $cell)
					{
						$cellval=trim($cell->getValue());
						$column = $cell->getColumn();
						//see if the cell has something for replacing
						if(preg_match_all("/\{".$data['id'].":(\w*|#\+?-?(\d*)?)\}/", $cellval, $matches))
						{
							//this cell has replacement tags
							if(!$foundTags) $foundTags=true;
							//remember the first ant the last row
							if($rowIndex!=$firstRow)
								$lastRow=$rowIndex;
							if($firstRow=='')
								$firstRow=$rowIndex;
						}
					}
				}
				
				//form the repeating range
				if($foundTags)
					$repeatRange=$firstCol.$firstRow.":".$lastCol.$lastRow;
				
				//set initial format data
				if(! isset($data['format']))
					$data['format']=array();
				
				//check if data is an array
				if(is_array($data['data']))
				{
					//every element is an array with data for all the columns
					if($foundTags)
					{
						//insert repeating rows, as many as needed
						//check if grouping is defined
						if(count($this->_group))
							$this->generateRepeatingRowsWithGrouping($data, $repeatRange);
						else
							$this->generateRepeatingRows($data, $repeatRange);
						//remove the template rows
						for($i=$firstRow;$i<=$lastRow;$i++)
						{
							$this->objWorksheet->removeRow($firstRow);
						}
						//if there is no data
						if(count($data['data'])==0)
							$this->addNoResultRow($firstRow,$firstCol,$lastCol);
					}
				}
				else
				{
				    //TODO
				    //maybe an SQL query?
				    //needs to be database agnostic
				}
				
			}
			else
			{
				//non-repeating data
				//check for additional formating
				if(! isset($data['format']))
					$data['format']=array();
				
				//check if data is an array or mybe a SQL query
				if(is_array($data['data']))
				{
					//array of data
					$this->generateSingleRow($data);
				}
				else
				{
					//TODO
				    //maybe an SQL query?
				    //needs to be database agnostic
				}
			}
			
			//call the replacing function
			$this->searchAndReplace();
		}
    }
    
    /**
     * Generates single non-repeating row of data
     * @param array $data 
     */
    private function generateSingleRow(& $data)
    {
        $id=$data['id'];
		$format=$data['format'];
		foreach($data['data'] as $key=>$value)
		{
			$search="{".$id.":".$key."}";
			$this->_search[]=$search;
			
			//if it needs formating
			if(isset($format[$key]))
			{
				foreach($format[$key] as $ftype=>$f)
				{
					if(preg_match("/\{(\w*)\}/", $f, $fmatches))
					{
						//if format is like {someColumn}
						if(isset($data['data'][$fmatches[1]]))
							$f=$data['data'][$fmatches[1]];
					}
					$value=$this->formatValue($value,$ftype,$f);
				}
			}
			$this->_replace[]=$value;
		}
    }
    
    /**
     * Generates repeating rows of data with some template range
     * @param array $data
     * @param string $repeatRange 
     */
	private function generateRepeatingRows(& $data, $repeatRange)
    {
        $rowCounter=0;
		$repeatTemplateArray=$this->objWorksheet->rangeToArray($repeatRange,null,true,true,true);
		//insert repeating rows but first check for minimum number of rows
		if(isset($data['minRows']))
		{
			$minRows=(int)$data['minRows'];
		}
		else
			$minRows=0;
		
		$lastRowFoundAt=end(array_keys($repeatTemplateArray));
		$firstRowFoundAt=reset(array_keys($repeatTemplateArray));
		$rowsFound=count($repeatTemplateArray);
		
		$mergeCells=$this->objWorksheet->getMergeCells();
		$needMerge=array();
		foreach($mergeCells as $mergeCell)
		{
			if($this->isSubrange($mergeCell, $repeatRange))
			{
				//contains merged cells, save for later
				$needMerge[]=$mergeCell;
			}
		}
		//check all the data
		foreach ($data['data'] as $value)
		{
			$rowCounter++;
			$skip=$rowCounter*$rowsFound;
			$newRowIndex=$firstRowFoundAt+$skip;
			
			//insert one or more rows if needed
			if($minRows<$rowCounter)
				$this->objWorksheet->insertNewRowBefore($newRowIndex,$rowsFound);
			
			//copy merge definitions
			foreach($needMerge as $nm)
			{
				$nm=PHPExcel_Cell::rangeBoundaries($nm);
				$newMerge=PHPExcel_Cell::stringFromColumnIndex($nm[0][0]-1).($nm[0][1]+$skip).":".PHPExcel_Cell::stringFromColumnIndex($nm[1][0]-1).($nm[1][1]+$skip);
				
				$this->objWorksheet->mergeCells($newMerge);
			}
			
			//generate row of data
			$this->generateSingleRepeatingRow($value, $repeatTemplateArray, $rowCounter, $skip, $data['id'], $data['format']);
		}
		//remove merge on template, BUG fix
		foreach($needMerge as $nm)
		{
			$this->objWorksheet->unmergeCells($nm);
		}
    }
    
    /**
     * Generates repeating rows of data with some template range but also with grouping
     * @param array $data
     * @param string $repeatRange 
     */
	private function generateRepeatingRowsWithGrouping(& $data, $repeatRange)
    {
        $rowCounter=0;
		$groupCounter=0;
		$footerCount=0;
		$repeatTemplateArray=$this->objWorksheet->rangeToArray($repeatRange,null,true,true,true);
		//insert repeating rows but first check for minimum number of rows
		if(isset($data['minRows']))
		{
			$minRows=(int)$data['minRows'];
		}
		else
			$minRows=0;
		
		$lastRowFoundAt=end(array_keys($repeatTemplateArray));
		$firstRowFoundAt=reset(array_keys($repeatTemplateArray));
		$rowsFound=count($repeatTemplateArray);
		
		list($rangeStart,$rangeEnd) = PHPExcel_Cell::rangeBoundaries($repeatRange);
		$firstCol=PHPExcel_Cell::stringFromColumnIndex($rangeStart[0]-1);
		$lastCol=PHPExcel_Cell::stringFromColumnIndex($rangeEnd[0]-1);
		
		$mergeCells=$this->objWorksheet->getMergeCells();
		$needMerge=array();
		foreach($mergeCells as $mergeCell)
		{
			if($this->isSubrange($mergeCell, $repeatRange))
			{
				//contains merged cells, save for later
				$needMerge[]=$mergeCell;
			}
		}
		
		//group array should have header, rows and summary elements
		foreach($this->_group['rows'] as $name=>$rows)
		{
			$groupCounter++;
			$caption=$this->_group['header'][$name];
			$newRowIndex=$firstRowFoundAt+$rowCounter*$rowsFound+$footerCount*$rowsFound+$groupCounter;
			//insert header for the group
			$this->objWorksheet->insertNewRowBefore($newRowIndex,1);
			$this->objWorksheet->setCellValue($firstCol.$newRowIndex,$caption);
			$this->objWorksheet->mergeCells($firstCol.$newRowIndex.":".$lastCol.$newRowIndex);

			//add style for the header
			
			$this->objWorksheet->getStyle($firstCol.$newRowIndex)->applyFromArray($this->headerGroupStyleArray);
			
			//add data for the group
			foreach ($rows as $row)
			{
				$value=$data['data'][$row];
				$rowCounter++;
				$skip=$rowCounter*$rowsFound+$footerCount*$rowsFound+$groupCounter;
				$newRowIndex=$firstRowFoundAt+$skip;

				//insert one or more rows if needed
				if($minRows<$rowCounter)
					$this->objWorksheet->insertNewRowBefore($newRowIndex,$rowsFound);

				//copy merge definitions
				foreach($needMerge as $nm)
				{
					$nm=PHPExcel_Cell::rangeBoundaries($nm);
					$newMerge=PHPExcel_Cell::stringFromColumnIndex($nm[0][0]-1).($nm[0][1]+$skip).":".PHPExcel_Cell::stringFromColumnIndex($nm[1][0]-1).($nm[1][1]+$skip);

					$this->objWorksheet->mergeCells($newMerge);
				}

				//generate row of data
				$this->generateSingleRepeatingRow($value, $repeatTemplateArray, $rowCounter, $skip, $data['id'], $data['format']);
			}
			
			//include the footer if defined
			if(isset($this->_group['summary']) && isset($this->_group['summary'][$name]))
			{
				$footerCount++;
				$skip=$groupCounter+$rowCounter*$rowsFound+$footerCount*$rowsFound;
				$newRowIndex=$firstRowFoundAt+$skip;
				
				$this->objWorksheet->insertNewRowBefore($newRowIndex,$rowsFound);
				$this->generateSingleRepeatingRow($this->_group['summary'][$name], $repeatTemplateArray, '', $skip, $data['id'], $data['format']);
				//add style for the footer
				
				$this->objWorksheet->getStyle($firstCol.$newRowIndex.":".$lastCol.$newRowIndex)->applyFromArray($this->footerGroupStyleArray);
			}
			
			//remove merge on template, BUG fix
			foreach($needMerge as $nm)
			{
				$this->objWorksheet->unmergeCells($nm);
			}
		}
    }
    
    /**
     * Generates single row for repeating data
     * @param array $value
     * @param array $repeatTemplateArray
     * @param int $rowCounter
     * @param int $skip
     * @param string $id
     * @param array $format 
     */
	private function generateSingleRepeatingRow(& $value, & $repeatTemplateArray, $rowCounter, $skip, $id, $format)
    {
        foreach($repeatTemplateArray as $rowKey=>$rowData)
        {
            foreach($rowData as $col=>$tag)
            {
                //$col is like A, B, C, ...
                //$rowKey is like 9,10,11, ...
                //$tag can have many replacement tags, e.g. "{item:item_id} --- {item:item_code}"

                if(preg_match_all("/\{".$id.":(\w*|#\+?-?(\d*)?)\}/", $tag, $matches))
                {
                    $matchTags=$matches[0]; //array with complete tags, e.g. '{item:item_id}'
                    $matchKeys=$matches[1]; //array with only the key names, e.g. 'item_id'
                    $matchNumber=count($matchTags); //how many replacement tags is there in this cell
                    $replaceTags=array();
                    $replaceValues=array();
                    foreach($matchKeys as $mkey)
                    {
                        $replaceTags[]="{".$id.":".$mkey."}";
                        if(strpos($mkey, "#")===0)
                        {
                            //this is a counter (optional offset)
                            $offset=explode("+", $mkey);
                            if(count($offset)>1)
                                $offset=$offset[1];
                            else
                                $offset=0;

                            $rValue=$rowCounter+(int)$offset;
                        }
                        elseif(key_exists($mkey, $value))
                        {
                            //format if needed
                            if(isset($format) && isset($format[$mkey]))
                            {
                                foreach($format[$mkey] as $ftype=>$f)
                                {
                                    if(preg_match("/\{(\w*)\}/", $f, $fmatches))
                                    {
                                        //if format is like {some_column}
                                        if(isset($value[$fmatches[1]]))
                                            $f=$value[$fmatches[1]];
                                    }
                                    $rValue=$this->formatValue($value[$mkey],$ftype,$f);
                                }
                            }
                            else
                            {
                                //without additional formating
                                $rValue=$value[$mkey];
                            }
                        }
                        else
                            $rValue=$mkey;

                        //add to replace array
                        $replaceValues[]=$rValue;
                    }
                    //replace all the values in this cell
                    $tag=str_replace($replaceTags,$replaceValues,$tag);
                }
                $newCellAddress=$col.($rowKey+$skip);
                $this->objWorksheet->setCellValue($newCellAddress,$tag);

                //copy cell styles
                $xfIndex=$this->objWorksheet->getCell($col.$rowKey)->getXfIndex();
                $this->objWorksheet->getCell($newCellAddress)->setXfIndex($xfIndex);

                //strip rows if requested
                if($this->_useStripRows && $rowCounter%2)
                {
                    $this->objWorksheet->getStyle($newCellAddress)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
                    $this->objWorksheet->getStyle($newCellAddress)->getFill()->getStartColor()->setRGB('F2F2F2');
                }
            }
        }
    }
    
    
	/**
	 * Check and apply various formating
	 */
    /**
     * Applies various formatings
     * Type can be datetime or number
     * @param mixed $value
     * @param string $type
     * @param mixed $format 
     */
	protected function formatValue($value,$type,$format)
    {
        if($type=='datetime')
		{
			//format can only be string
			if(is_string($format))
				$value=date($format, strtotime($value));
		}
		elseif($type=='number')
		{
			//format must be an array
			if(is_array($format))
			{
				//set the defaults
				if(!isset($format['prefix']))
					$format['prefix']='';
				if(!isset($format['decimals']))
					$format['decimals']=0;
				if(!isset($format['decPoint']))
					$format['decPoint']='.';
				if(!isset($format['thousandsSep']))
					$format['thousandsSep']=',';
				if(!isset($format['sufix']))
					$format['sufix']='';
				$value=$format['prefix'].number_format($value,$format['decimals'],$format['decPoint'],$format['thousandsSep']).$format['sufix'];
			}
		}
		
		return $value;
    }
    
    /** 
	 * Replaces all the cells with real data
	 */
	private function searchAndReplace()
    {
        foreach ($this->objWorksheet->getRowIterator() as $row)
		{
			$cellIterator = $row->getCellIterator();
			foreach ($cellIterator as $cell)
			{
				$cell->setValue(str_replace($this->_search, $this->_replace, $cell->getValue()));
			}
		}
    }
    
    /**
     * Adda a row for repeating data when there is no results
     * @param int $rowIndex
     * @param string $colMin
     * @param string $colMax 
     */
    private function addNoResultRow($rowIndex,$colMin,$colMax)
    {
        //insert one row
		$this->objWorksheet->insertNewRowBefore($rowIndex);
		
		//merge as required
		$this->objWorksheet->mergeCells($colMin.$rowIndex.":".$colMax.$rowIndex);
		
		//insert text
		
		$this->objWorksheet->setCellValue($colMin.$rowIndex, $this->_noResultText);
		
		$this->objWorksheet->getStyle($colMin.$rowIndex.":".$colMax.$rowIndex)->applyFromArray($this->noResultStyleArray);
    }
    
    /**
	 * Generates heading title of the report
	 */
    private function generateHeading()
    {
        //get current dimensions
		$highestRow = $this->objWorksheet->getHighestRow(); // e.g. 10
		$highestColumn = $this->objWorksheet->getHighestColumn(); // e.g 'F'
		
		$highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn); 

		//insert row on top
		$this->objWorksheet->insertNewRowBefore(1,2);
		
		//merge cells
		$this->objWorksheet->mergeCells("A1:".$highestColumn."1");
		
		//set the text for header
		$this->objWorksheet->setCellValue("A1", $this->_headingText);
		$this->objWorksheet->getStyle('A1')->getAlignment()->setWrapText(true);
		$this->objWorksheet->getRowDimension('1')->setRowHeight(48);
		
        //Apply style
		$this->objWorksheet->getStyle("A1")->applyFromArray($this->headingStyleArray);
    }
    
    /**
     * Renders report as specified output file
     * @param string $type
     * @param string $filename 
     */
    public function render($type='html',$filename='')
    {
        if($type=='')
			$type="html";
		
		if($filename=='')
			$filename="Report ".date("Y-m-d");
		else
		    $filename=strftime($filename);
		//http://strftime.net/
		

		if(strtolower($type)=='html')
			return $this->renderHtml();
		elseif(strtolower($type)=='excel')
			return $this->renderXlsx($filename);
		elseif(strtolower($type)=='excel2003')
			return $this->renderXls($filename);
		elseif(strtolower($type)=='pdf')
			return $this->renderPdf($filename);
		else
			return "Error: unsupported export type!"; //TODO: better error handling
    }
    
    /**
     * Renders report as a HTML output
	 */
	private function renderHtml()
    {
        $this->objWriter = new PHPExcel_Writer_HTML($this->objPHPExcel);

		// Generate HTML
		$html = '';
		$html .= $this->objWriter->generateHTMLHeader(true);
		$html .= $this->objWriter->generateSheetData();
		$html .= $this->objWriter->generateHTMLFooter();
		$html .= '';
		$this->objPHPExcel->disconnectWorkSheets();
		unset($this->objWriter);
		unset($this->objWorksheet);
		unset($this->objReader);
		unset($this->objPHPExcel);
		
		return $html;
    }
    
    /**
     * Renders report as a XLSX file
	 */
	private function renderXlsx($filename)
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
		header('Cache-Control: max-age=0');

		$this->objWriter = PHPExcel_IOFactory::createWriter($this->objPHPExcel, 'Excel2007');

        $this->objWriter->save('php://output');
		unset($this->objWriter);
		unset($this->objWorksheet);
		unset($this->objReader);
		unset($this->objPHPExcel);
		exit();
    }
    
	/**
     * Renders report as a XLS file
	 */
	private function renderXls($filename)
    {
        header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="'.$filename.'.xls"');
		header('Cache-Control: max-age=0');

		$this->objWriter = PHPExcel_IOFactory::createWriter($this->objPHPExcel, 'Excel5');

        $this->objWriter->save('php://output');
		unset($this->objWriter);
		unset($this->objWorksheet);
		unset($this->objReader);
		unset($this->objPHPExcel);
		exit();
    }
    
    /**
     * Renders report as a PDF file
	 */
	private function renderPdf($filename)
	{
        header('Content-Type: application/vnd.pdf');
		header('Content-Disposition: attachment;filename="'.$filename.'.pdf"');
		header('Cache-Control: max-age=0');
		
		$this->objWriter = PHPExcel_IOFactory::createWriter($this->objPHPExcel, 'PDF');

        $this->objWriter->save('php://output');
		exit();
    }
    
	/**
	 * Helper function for checking subranges of a range
	 */
	public function isSubrange($subRange,$range)
	{
		list($rangeStart,$rangeEnd) = PHPExcel_Cell::rangeBoundaries($range);
		list($subrangeStart,$subrangeEnd) = PHPExcel_Cell::rangeBoundaries($subRange);
		return (($subrangeStart[0]>=$rangeStart[0]) && ($subrangeStart[1]>=$rangeStart[1]) && ($subrangeEnd[0]<=$rangeEnd[0]) && ($subrangeEnd[1]<=$rangeEnd[1]));
	}
	
	/**
	 * Enabling strip rows
	 */
	function enableStripRows()
	{
		$this->_useStripRows=true;
	}
	
	/**
	 * Sets title of the report header
	 */
	function setHeading($h)
	{
		$this->_headingText=$h;
	}
	
}