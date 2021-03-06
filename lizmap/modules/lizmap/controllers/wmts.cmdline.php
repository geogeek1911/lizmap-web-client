<?php
/**
* @package   lizmap
* @subpackage lizmap
* @author    your name
* @copyright 2011 3liz
* @link      http://3liz.com
* @license    All rights reserved
*/
require (JELIX_LIB_CORE_PATH.'request/jClassicRequest.class.php');

class jCoordinatorForTest extends jCoordinator {
    function testSetRequest($request) {
        $this->setRequest($request);
    }
}

class wmtsCtrl extends jControllerCmdLine {

    /**
    * Options to the command line
    *  'method_name' => array('-option_name' => true/false)
    * true means that a value should be provided for the option on the command line
    */
    protected $allowed_options = array(
        'capabilities' => array(
            '-v'=>False
        ),
        'seeding' => array(
            '-v'=>False,
            '-f'=>False
        )
    );

    /**
     * Parameters for the command line
     * 'method_name' => array('parameter_name' => true/false)
     * false means that the parameter is optional. All parameters which follow an optional parameter
     * is optional
     */
    protected $allowed_parameters = array(
        'capabilities' => array(
            'repository'=>True,
            'project'=>True,
            'layer'=>False,
            'TileMatrixSet'=>False
        ),
        'seeding' => array(
            'repository'=>True,
            'project'=>True,
            'layers'=>True,
            'TileMatrixSet'=>True,
            'TileMatrixMin'=>True,
            'TileMatrixMax'=>True
        )
    );

    /**
     * Help
     *
     *
     */
    public $help = array(
        'capabilities' => 'Get seeding capabilities
    options:
        -v  verbose, provide tiles count for each TileMatrix
    parameters:
        repository      the repository id
        project         the project name
    optional prameters:
        layer           the layer name for which you want to know capabilities
        TileMatrixSet   the TileMatrixSet for which you want to know capabilites
    Use:
        php lizmap/scripts/script.php lizmap~wmts:capabilities [-v] repository project [layer] [TileMatrixSet]
        ',
        'seeding' => 'Generate cache for a layer
    options:
        -v  verbose
        -f  forced the cache generation, overwrite already done cache
    parameters:
        repository      the repository id
        project         the project name
        layers          the layer name list for which you want to generate the cache
        TileMatrixSet   the TileMatrixSet for which you want to generate the cache. The TileMatrixSet is a CRS
        TileMatrixMin   the min zoom level to generate
        TileMatrixMax   the min zoom level to generate
    Use:
        php lizmap/scripts/script.php lizmap~wmts:seeding [-v] [-f] repository project layer TileMatrixSet TileMatrixMin TileMatrixMax
        '
    );

    /**
    *
    */
    function capabilities() {
        $fakeServer = new jelix\FakeServerConf\ApacheMod(jApp::wwwPath(), '/index.php');

        $verbose = $this->option('-v');

        $rep = $this->getResponse(); // cmdline response by default

        $project = lizmap::getProject($this->param('repository').'~'.$this->param('project'));
        // Project not found
        if ( !$project ) {
            $rep->addContent("The project has not be found!\n");
            return $rep;
        }
        $repository = $project->getRepository();

        jClasses::inc('lizmap~lizmapWMTSRequest');

        $cacheId = $repository->getKey().'_'.$project->getKey().'_WMTS';
        $tileMatrixSetList = jCache::get($cacheId . '_tileMatrixSetList');
        if( !$tileMatrixSetList ) {
            $request = new lizmapWMTSRequest( $project, array(
                    'service'=>'WMTS',
                    'request'=>'GetCapabilities'
                )
            );
            $result = $request->process();
            $tileMatrixSetList = jCache::get($cacheId . '_tileMatrixSetList');
        }

        $layerId = $this->param('layer');
        $TileMatrixSetId = $this->param('TileMatrixSet');

        $layers = $tileMatrixSetList = jCache::get($cacheId . '_layers');
        foreach( $layers as $layer ) {
            if ( $layerId && $layer->name != $layerId )
                continue;
            foreach( $layer->tileMatrixSetLinkList as $tileMatrixSetLink ) {
                if ( $TileMatrixSetId && $tileMatrixSetLink->ref != $TileMatrixSetId )
                    continue;
                if ( $verbose ) {
                    foreach ( $tileMatrixSetLink->tileMatrixLimits as $tileMatrixLimit ) {
                        $tmCount = ($tileMatrixLimit->maxRow - $tileMatrixLimit->minRow + 1) * ($tileMatrixLimit->maxCol - $tileMatrixLimit->minCol + 1);
                        $rep->addContent('For "'.$layer->name.'" and "'.$tileMatrixSetLink->ref.'" the TileMatrix '.$tileMatrixLimit->id.' has '.$tmCount.' tiles'."\n");
                    }
                } else {
                    $tmls = array();
                    foreach ( $tileMatrixSetLink->tileMatrixLimits as $tileMatrixLimit ) {
                        $tmls[] = $tileMatrixLimit->id;
                    }
                    $rep->addContent('For "'.$layer->name.'" and "'.$tileMatrixSetLink->ref.'" from TileMatrix '.min($tmls).' to '.max($tmls)."\n");
                }
            }
        }

        return $rep;
    }

    /**
    *
    */
    function seeding() {
        $fakeServer = new jelix\FakeServerConf\ApacheMod(jApp::wwwPath(), '/index.php');

        $forced = $this->option('-f');
        $verbose = $this->option('-v');

        $rep = $this->getResponse(); // cmdline response by default

        $project = lizmap::getProject($this->param('repository').'~'.$this->param('project'));
        // Project not found
        if ( !$project ) {
            $rep->addContent("The project has not be found!\n");
            return $rep;
        }
        $repository = $project->getRepository();

        jClasses::inc('lizmap~lizmapWMTSRequest');

        $cacheId = $repository->getKey().'_'.$project->getKey().'_WMTS';
        $tileMatrixSetList = jCache::get($cacheId . '_tileMatrixSetList');
        if( !$tileMatrixSetList ) {
            $request = new lizmapWMTSRequest( $project, array(
                    'service'=>'WMTS',
                    'request'=>'GetCapabilities'
                )
            );
            $result = $request->process();
            $tileMatrixSetList = jCache::get($cacheId . '_tileMatrixSetList');
        }

        $layers = $tileMatrixSetList = jCache::get($cacheId . '_layers');
        $layerIds = explode( ',', $this->param('layers') );
        $selectedLayers = array();
        foreach( $layers as $l ) {
            if ( in_array( '*', $layerIds) || in_array( $l->name, $layerIds) ) {
                $selectedLayers[] = $l;
            }
        }
        // Layer not found
        if ( count( $selectedLayers ) == 0 ) {
            $rep->addContent("The layers '".implode( ',', $layerIds )."' have not be found!\n");
            return $rep;
        }

        foreach( $selectedLayers as $layer ) {
            $TileMatrixSetId = $this->param('TileMatrixSet');
            $tileMatrixSetLink = null;
            foreach( $layer->tileMatrixSetLinkList as $tms ) {
                if ( $tms->ref == $TileMatrixSetId ) {
                    $tileMatrixSetLink = $tms;
                    break;
                }
            }
            // TileMatrixSet not found
            if ( !$tileMatrixSetLink ) {
                $rep->addContent("The TileMatrixSet '".$TileMatrixSetId."' has not be found!\n");
                continue;
            }

            $TileMatrixMin = (int)$this->param('TileMatrixMin');
            $TileMatrixMax = (int)$this->param('TileMatrixMax');
            // count tiles
            $tileCount = 0;
            foreach ( $tileMatrixSetLink->tileMatrixLimits as $tileMatrixLimit ) {
                if ( $tileMatrixLimit->id >= $TileMatrixMin && $tileMatrixLimit->id <= $TileMatrixMax ) {
                    $tmCount = ($tileMatrixLimit->maxRow - $tileMatrixLimit->minRow + 1) * ($tileMatrixLimit->maxCol - $tileMatrixLimit->minCol + 1);
                    if ( $verbose )
                        $rep->addContent($tmCount.' tiles to generate for "'.$layer->name.'" "'.$TileMatrixSetId.'" "'. $tileMatrixLimit->id.'"'."\n");
                    $tileCount += $tmCount;
                }
            }
            if ( $verbose ) {
                $rep->addContent($tileCount.' tiles to generate for "'.$layer->name.'" "'.$TileMatrixSetId.'" between "'. $TileMatrixMin.'" and "'. $TileMatrixMax.'"'."\n");
            }

            // generate tiles
            $rep->addContent("Start generation\n");
            $rep->addContent("================\n");
            $tileProgress = 0;
            $tileStepHeight = max( 5.0, floor(5*100/$tileCount) ) ;
            $tileStep = $tileStepHeight;
            foreach ( $tileMatrixSetLink->tileMatrixLimits as $tileMatrixLimit ) {
                if ( $tileMatrixLimit->id >= $TileMatrixMin && $tileMatrixLimit->id <= $TileMatrixMax ) {
                    $row = (int) $tileMatrixLimit->minRow;
                    //$rep->addContent( $tileMatrixLimit->id.' '.$tileMatrixLimit->minRow.' '.$tileMatrixLimit->maxRow.' '.$tileMatrixLimit->minCol.' '.$tileMatrixLimit->maxCol."\n");
                    while ( $row <= $tileMatrixLimit->maxRow ) {
                        $col = (int) $tileMatrixLimit->minCol;
                        while ( $col <= $tileMatrixLimit->maxCol ) {
                            $request = new lizmapWMTSRequest( $project, array(
                                    'service'=>'WMTS',
                                    'version'=>'1.0.0',
                                    'request'=>'GetTile',
                                    'layer'=>$layer->name,
                                    'format'=>$layer->imageFormat,
                                    'TileMatrixSet'=>$TileMatrixSetId,
                                    'TileMatrix'=>$tileMatrixLimit->id,
                                    'TileRow'=>$row,
                                    'TileCol'=>$col
                                )
                            );
                            if ( $forced )
                                $request->setForceRequest( True );
                            $result = $request->process();
                            //$rep->addContent($layer->name.' '.$layer->imageFormat.' '.$TileMatrixSetId.' '.$tileMatrixLimit->id.' '.$row.' '.$col.' '.$result->code."\n");
                            $col += 1;
                            $tileProgress += 1;
                            if ( $verbose && $tileProgress * 100 / $tileCount >= $tileStep ) {
                                $tileStep = floor($tileProgress * 100 / $tileCount);
                                $rep->addContent('Progression: '.$tileStep.'%, '.$tileProgress.' tiles generated on '.$tileCount.' tiles'."\n");
                                $tileStep = $tileStep + $tileStepHeight;
                            }
                        }
                        $row += 1;
                    }
                }
            }
            $rep->addContent("================\n");
            $rep->addContent("End generation\n");
        }
        return $rep;
    }
}
