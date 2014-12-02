<?php
/*******************************************************************************
Go Search Engine - Benchmarking
****************************************************************************//**

G_Benchmark can be used to perform some simple benchmark tests.

Usage:

	$obj = new G_Benchmark;
	$obj->start();
				// the time between these calls is calculated
	$obj->stop;
				// time consumed here is not added
	$obj->start();
				// the time betweend these calls will be adde to the total amount
	$obj->stop;
	
@author Björn Petersen

*******************************************************************************/

class G_Benchmark
{
	private $total_time = 0.0;
	private $start_time = 0.0;

	function start()
	{
		$this->start_time = microtime(true);
	}
	
	function stop()
	{
		if( $this->start_time > 0.0 ) {
			$this->total_time += microtime(true) - $this->start_time;
			$this->start_time = 0.0;
		}
	}
	
	function get()
	{
		$temp_time = $this->total_time;
		if( $this->start_time > 0.0 ) {
			$temp_time += microtime(true) - $this->start_time;
		}
		return $temp_time;
	}
};
