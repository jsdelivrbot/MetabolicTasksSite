@extends('layouts.master')
@section('content')
  <div class="row align-justify collapse">
    <div class="columns shrink"><h4>{{ $runName }}</h4></div>
    <div class="columns shrink">
      <a id="re-run" class="button warning bold" href="/rerun/{{ $hash }}" >Rerun With New Parameters</a>
      <a id="download-archive" class="button success bold" href="/run/download/{{ $hash }}" download>Download Results Archive</a>
    </div>
  </div>


<div class="heatmap"></div>

<meta charset="utf-8">
<style>
  .axis path,
  .axis line {
    fill: none;
    stroke: black;
    shape-rendering: crispEdges;
  }

  .axis text {
      font-family: sans-serif;
      font-size: 11px;
  }
</style>

<!--   </head>
  <div id="tooltip" class="hidden">
          <p><span id="value"></p>
  </div>
  <script src="http://d3js.org/d3.v3.min.js"></script>
  Order: 
    <select id="order">
    <option value="hclust">by cluster</option>
    <option value="probecontrast">by probe name and contrast name</option>
    <option value="probe">by probe name</option>
    <option value="contrast">by contrast name</option>
    <option value="custom">by log2 ratio</option>
    </select>
    </select>
  <div id="chart" style='overflow:auto; width:960px; height:480px;'></div> -->



@stop

@section('customScripts')
    <script src="//cdnjs.cloudflare.com/ajax/libs/d3/3.5.5/d3.min.js"></script>
    <script>
      d3.csv('{{"/run-images?path=".urlencode("/$hash/workingDir/Analysis/Output/score.long.csv")}}', function (myArrayOfObjects){
        myArrayOfObjects.forEach(function (d){
          console.log(d);
        });
      });
    </script>


<script src="//d3js.org/d3.v3.min.js"></script>

<script>
  var itemSize = 22,
      cellSize = itemSize - 1,
      margin = {top: 120, right: 20, bottom: 20, left: 110};
      
  var width = 750 - margin.right - margin.left,
      height = 300 - margin.top - margin.bottom;

  var formatDate = d3.time.format("%Y-%m-%d");

  d3.csv('{{"/run-images?path=".urlencode("/$hash/workingDir/Analysis/Output/score.long.csv")}}', function ( response ) {

    var data = response.map(function( item ) {
        var newItem = {};
        newItem.country = item[0];
        newItem.product = item[1];
        newItem.value = item[2];
        console.log(newItem)

        return newItem;
    })

    var x_elements = d3.set(data.map(function( item ) { return item.product; } )).values(),
        y_elements = d3.set(data.map(function( item ) { return item.country; } )).values();

    var xScale = d3.scale.ordinal()
        .domain(x_elements)
        .rangeBands([0, x_elements.length * itemSize]);

    var xAxis = d3.svg.axis()
        .scale(xScale)
        .tickFormat(function (d) {
            return d;
        })
        .orient("top");

    var yScale = d3.scale.ordinal()
        .domain(y_elements)
        .rangeBands([0, y_elements.length * itemSize]);

    var yAxis = d3.svg.axis()
        .scale(yScale)
        .tickFormat(function (d) {
            return d;
        })
        .orient("left");

    var colorScale = d3.scale.threshold()
        .domain([0.85, 1])
        .range(["#2980B9", "#E67E22", "#27AE60", "#27AE60"]);

    var svg = d3.select('.heatmap')
        .append("svg")
        .attr("width", width + margin.left + margin.right)
        .attr("height", height + margin.top + margin.bottom)
        .append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

    var cells = svg.selectAll('rect')
        .data(data)
        .enter().append('g').append('rect')
        .attr('class', 'cell')
        .attr('width', cellSize)
        .attr('height', cellSize)
        .attr('y', function(d) { return yScale(d.country); })
        .attr('x', function(d) { return xScale(d.product); })
        .attr('fill', function(d) { return colorScale(d.value); });

    svg.append("g")
        .attr("class", "y axis")
        .call(yAxis)
        .selectAll('text')
        .attr('font-weight', 'normal');

    svg.append("g")
        .attr("class", "x axis")
        .call(xAxis)
        .selectAll('text')
        .attr('font-weight', 'normal')
        .style("text-anchor", "start")
        .attr("dx", ".8em")
        .attr("dy", ".5em")
        .attr("transform", function (d) {
            return "rotate(-65)";
        });
  });
</script>
@parent
@stop