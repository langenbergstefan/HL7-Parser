<?php
namespace HL7;
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 2004 D.A.Dokter                                        |
// +----------------------------------------------------------------------+
// | This source file is subject to version 3.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.php.net/license/3_0.txt.                                  |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: D.A.Dokter <dokter@w20e.com>                                |
// +----------------------------------------------------------------------+
//
// $Id: Message.php,v 1.8 2004/08/06 07:38:54 wyldebeast Exp $


use \RuntimeException;
use \InvalidArgumentException;

/**
 * Class specifying the HL7 message, both request and response.
 *
 * In general one needn't create an instance of the Net_HL7_Message
 * class directly, but use the Net_HL7 factory class to create one.
 * When adding segments, note that the segment index starts
 * at 0, so to get the first segment, do
 * <code>$msg->getSegmentByIndex(0)</code>.
 *
 * The segment separator defaults to \015. To change this, set the
 * global variable $_Net_HL7_SEGMENT_SEPARATOR.
 *
 * @version    $Revision: 1.8 $
 * @author     D.A.Dokter <dokter@w20e.com>
 * @access     public
 * @category   Networking
 * @package    Net_HL7
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 */
class Message {

    /**
     * Array holding all segements of this message.
     */
    public $_segments;

    /**
     * local value for segment separator
     */
    public $_segmentSeparator;
    public $_fieldSeparator;
    public $_componentSeparator;
    public $_subcomponentSeparator;
    public $_repetitionSeparator;
    public $_escapeChar;
    public $_hl7Version;


    /**
     * Constructor for Net_HL7_Message. Consider using the HL7 factory
     * to obtain a message instead.
     *
     * The constructor takes an optional string argument that is a string
     * representation of a HL7 message. If the string representation is not a
     * valid HL7 message. according to the specifications, undef is returned
     * instead of a new instance. This means that segments should be
     * separated within the message with the segment separator (defaults to
     * \015) or a newline, and segments should be syntactically correct.
     * When using the string argument constructor, make sure that you have
     * escaped any characters that would have special meaning in PHP.
     *
     * The control characters and field separator will take the values from
     * the MSH segment, if set. Otherwise defaults will be used. Changing the
     * MSH fields specifying the field separator and control characters after
     * the MSH has been added to the message will result in setting these
     * values for the message.
     *
     * If the message couldn't be created, for example due to a erroneous HL7
     * message string, an error is raised.
     *
     * @throws RuntimeException
     */
    public function __construct($msgStr = "", $hl7Globals = array())
    {

        //Array holding the segments
        $this->_segments = array();

        // Control characters and other HL7 properties
        //
        !empty($hl7Globals["SEGMENT_SEPARATOR"]) ?
            $this->_segmentSeparator = $hl7Globals["SEGMENT_SEPARATOR"] :
            $this->_segmentSeparator = "\015";
        !empty($hl7Globals["FIELD_SEPARATOR"]) ?
            $this->_fieldSeparator = $hl7Globals["FIELD_SEPARATOR"] :
            $this->_fieldSeparator = "|";
        !empty($hl7Globals["COMPONENT_SEPARATOR"]) ?
            $this->_componentSeparator = $hl7Globals["COMPONENT_SEPARATOR"] :
            $this->_componentSeparator = "^";
        !empty($hl7Globals["SUBCOMPONENT_SEPARATOR"]) ?
            $this->_subcomponentSeparator = $hl7Globals["SUBCOMPONENT_SEPARATOR"] :
            $this->_subcomponentSeparator = "&";
        !empty($hl7Globals["REPETITION_SEPARATOR"]) ?
            $this->_repetitionSeparator = $hl7Globals["REPETITION_SEPARATOR"] :
            $this->_repetitionSeparator = "~";
        !empty($hl7Globals["ESCAPE_CHAR"]) ?
            $this->_escapeChar = $hl7Globals["ESCAPE_CHAR"] :
            $this->_escapeChar = "\\";
        !empty($hl7Globals["HL7_VERSION"]) ?
            $this->_hl7Version = $hl7Globals["HL7_VERSION"] :
            $this->_hl7Version = "2.2";


        // If an HL7 string is given to the constructor, parse it.
        if ($msgStr) {

            $segments = preg_split("/[\n\\" . $this->_segmentSeparator . "]/", $msgStr, -1, PREG_SPLIT_NO_EMPTY);

            // The first segment should be the control segment
            //
            preg_match("/^([A-Z0-9]{3})(.)(.)(.)(.)(.)(.)/", $segments[0], $matches);

            $hdr = $matches[1];
            $fldSep = $matches[2];
            $compSep = $matches[3];
            $repSep = $matches[4];
            $esc = $matches[5];
            $subCompSep = $matches[6];
            $fldSepCtrl = $matches[7];

            // Check whether field separator is repeated after 4 control characters
            //
            if ($fldSep != $fldSepCtrl) {

                throw new RuntimeException("Not a valid message: field separator invalid");
            }

            // Set field separator based on control segment
            $this->_fieldSeparator        = $fldSep;

            // Set other separators
            $this->_componentSeparator    = $compSep;
            $this->_subcomponentSeparator = $subCompSep;
            $this->_escapeChar            = $esc;
            $this->_repetitionSeparator   = $repSep;

            // Do all segments
            //
            for ($i = 0; $i < count($segments); $i++) {

                $fields = preg_split("/\\" . $this->_fieldSeparator . "/", $segments[$i]);
                $name = array_shift($fields);

                // Now decompose fields if necessary, into arrays
                //
                for ($j = 0; $j < count($fields); $j++) {

                    // Skip control field
                    if ($i == 0 && $j == 0) {
                        continue;
                    }

					// correction by langenbergstefan                    
                    // $comps = preg_split("/\\" . $this->_componentSeparator ."/", $fields[$j], -1, PREG_SPLIT_NO_EMPTY);
						  $comps = preg_split("/\\" . $this->_componentSeparator ."/", $fields[$j], -1);

                    for ($k = 0; $k < count($comps); $k++) {

                        $subComps = preg_split("/\\" . $this->_subcomponentSeparator . "/", $comps[$k]);

                        // Make it a ref or just the value
                        (count($subComps) == 1) ? ($comps[$k] = $subComps[0]) : ($comps[$k] = $subComps);
                    }

                    (count($comps) == 1) ? ($fields[$j] = $comps[0]) : ($fields[$j] = $comps);
                }

                $seg = null;
                $segClass = "HL7\\Segments\\$name";

                // Let's see whether it's the a special segment
                //
                if (class_exists('HL7\\Segments\\' . $name)) {
                    array_unshift($fields, $this->_fieldSeparator);

                    $seg = new $segClass($fields);
                } else {
                    $seg = new Segment($name, $fields);
                }

                if (!$seg) {
                    throw new RuntimeException("Segment not created");
                }

                $this->addSegment($seg);
            }
        }
    }


    /**
     * Add a segment.
     *
     * The segment will be added to the end of the message. The
     * segment should be an instance of HL7\Segment.
     *
     * @param Segment
     * @return boolean
     * @access public
     * @throws InvalidArgumentException
     * @see Segment
     */
    public function addSegment($segment)
    {
        if (!($segment instanceof Segment)) {
            throw new InvalidArgumentException("The object is not an instance of Segment");
        }

        if (count($this->_segments) == 0) {
            $this->_resetCtrl($segment);
        }

        array_push($this->_segments, $segment);

        return true;
    }


    /**
     * Insert a segment.
     *
     * The segment should be an instance of Net_HL7_Segment. If the
     * index is not given, nothing happens.
     *
     * @param object An instance of Net_HL7_Segment
     * @param int Index where segment is inserted
     * @return boolean
     * @access public
     * @throws InvalidArgumentException
     * @see Net_HL7_Segment
     */
    public function insertSegment($segment, $idx = null)
    {
        if ((!$idx) || ($idx > count($this->_segments))) {
            throw new InvalidArgumentException("Index '$idx' is out of range");
        }

        if (!($segment instanceof Segment)) {
            throw new InvalidArgumentException("The object is not an instance of Segment");
        }

        if ($idx == 0) {
            $this->_resetCtrl($segment);
            array_unshift($this->_segments, $segment);
        } elseif ($idx == count($this->_segments)) {
            array_push($this->_segments, $segment);
        } else {
            $this->_segments =
                array_merge(
                            array_slice($this->_segments, 0, $idx),
                            array($segment),
                            array_slice($this->_segments, $idx)
                            );
        }

        return true;
    }


    /**
     * Return the segment specified by $index.
     *
     * Segment count within the message starts at 0.
     *
     * @param int Index where segment is inserted
     * @return Segment
     * @access public
     * @see Segment
     */
    public function getSegmentByIndex($index)
    {
        if ($index >= count($this->_segments)) {
            return NULL;
        }

        return $this->_segments[$index];
    }


    /**
     * Return an array of all segments with the given name
     *
     * @param mixed Segment name
     * @return array List of segments identified by name
     * @access public
     */
    public function getSegmentsByName($name)
    {
        $segmentsByName = array();

        foreach ($this->_segments as $seg) {

            if ($seg->getName() == $name) {
                array_push($segmentsByName, $seg);
            }
        }

        return $segmentsByName;
    }


    /**
     * Remove the segment indexed by $index.
     *
     * If it doesn't exist, nothing happens, if it does, all segments
     * after this one will be moved one index up.
     *
     * @param int Index where segment is removed
     * @return boolean
     * @access public
     */
    public function removeSegmentByIndex($index)
    {
        if ($index < count($this->_segments)) {
            array_splice($this->_segments, $index, 1);
        }

        return true;
    }


    /**
     * Set the segment on index.
     *
     * If index is out of range, or not provided, do nothing. Setting
     * MSH on index 0 will revalidate field separator, control
     * characters and hl7 version, based on MSH(1), MSH(2) and
     * MSH(12).
     *
     * @param Segment
     * @param int Index where segment is set
     * @return boolean
     * @access public
     * @throws InvalidArgumentException
     * @see Segment
     */
    public function setSegment($segment, $idx)
    {
        if ((!isset($idx)) || $idx > count($this->_segments)) {
            throw new InvalidArgumentException("Index '$idx' is out of range");
        }

        if (!($segment instanceof Segment)) {
            throw new InvalidArgumentException("The object is not an instance of Segment");
        }

        if ($segment->getName() == "MSH" && $idx == 0) {
            $this->_resetCtrl($segment);
        }

        $this->_segments[$idx] = $segment;

        return true;
    }


    /**
     * After change of MSH, reset control fields
     *
     * @param object An instance of Net_HL7_Segment
     * @return boolean
     * @access private
     * @throws InvalidArgumentException
     * @see Segment
     */
    public function _resetCtrl($segment)
    {
        if (!($segment instanceof Segment)) {
            throw new InvalidArgumentException("The object is not an instance of Segment");
        }

        if ($segment->getField(1)) {
            $this->_fieldSeparator = $segment->getField(1);
        }

        if (preg_match("/(.)(.)(.)(.)/", $segment->getField(2), $matches)) {
            $this->_componentSeparator    = $matches[1];
            $this->_repetitionSeparator   = $matches[2];
            $this->_escapeChar            = $matches[3];
            $this->_subcomponentSeparator = $matches[4];
        }

        if ($segment->getField(12)) {
            $this->hl7Version = $segment->getField(12);
        }

        return true;
    }


    /**
     * Return an array containing all segments in the right order.
     *
     * @return array List of all segments
     * @access public
     */
    public function getSegments()
    {
        return $this->_segments;
    }


    /**
     * Return a string representation of this message.
     *
     * This can be used to send the message over a socket to an HL7
     * server. To print to other output, use the $pretty argument as
     * some true value. This will not use the default segment
     * separator, but '\n' instead.
     *
     * @param boolean Whether to use \n as separator or default (\r).
     * @return mixed String representation of HL7 message
     * @access public
     */
    public function toString($pretty = False)
    {
        $msg = "";

        // Make sure MSH(1) and MSH(2) are ok, even if someone has changed
        // these values
        //
        $msh = $this->_segments[0];

        $this->_resetCtrl($msh);

        foreach ($this->_segments as $seg) {

            $msg .= $seg->getName() . $this->_fieldSeparator;

            foreach ($seg->getFields(($seg->getName() != "MSH" ? 1 : 2)) as $fld) {

                if (is_array($fld)) {

                    for ($i = 0; $i < count($fld); $i++) {

                        (is_array($fld[$i])) ? ($msg .= join($this->_subcomponentSeparator, $fld[$i])) :
                            ($msg .= $fld[$i]);


                        if ($i < (count($fld) - 1)) {
                            $msg .= $this->_componentSeparator;
                        }
                    }
                } else {
                    $msg .= $fld;
                }

                $msg .= $this->_fieldSeparator;
            }

            ($pretty) ? ($msg .= "\n") : ($msg .= $this->_segmentSeparator);
        }

        return $msg;
    }


    /**
     * Get the segment identified by index as string, using the
     * messsages separators.
     *
     * @param int Index for segment to get
     * @return mixed String representation of segment
     * @access public
     * @see Net_HL7_Segment
     */
    public function getSegmentAsString($index)
    {
        $seg = $this->getSegmentByIndex($index);

        if ($seg == NULL) {
            return NULL;
        }

        $segStr = $seg->getName() . $this->_fieldSeparator;

        foreach ($seg->getFields(($seg->getName() != "MSH" ? 1 : 2)) as $fld) {

            if (is_array($fld)) {

                for ($i = 0; $i < count($fld); $i++) {

                    (is_array($fld[$i])) ? ($segStr .= join($this->_subcomponentSeparator, $fld[$i])) :
                        ($segStr .= $fld[$i]);


                    if ($i < (count($fld) - 1)) {
                        $segStr .= $this->_componentSeparator;
                    }
                }
            } else {
                $segStr .= $fld;
            }

            $segStr .= $this->_fieldSeparator;
        }

        return $segStr;
    }


    /**
     * Get the field identified by $fldIndex from segment $segIndex.
     *
     * Returns empty string if field is not set.
     *
     * @param int Index for segment to get
     * @param int Index for field to get
     * @return mixed String representation of field
     * @access public
     */
    public function getSegmentFieldAsString($segIndex, $fldIndex)
    {
        $seg = $this->getSegmentByIndex($segIndex);

        if ($seg == NULL) {
            return NULL;
        }

        $fld = $seg->getField($fldIndex);

        if (!$fld) {
            return "";
        }

        $fldStr = "";

        if (is_array($fld)) {

            for ($i = 0; $i < count($fld); $i++) {

                (is_array($fld[$i])) ? ($fldStr .= join($this->_subcomponentSeparator, $fld[$i])) :
                    ($fldStr .= $fld[$i]);


                if ($i < (count($fld) - 1)) {
                    $fldStr .= $this->_componentSeparator;
                }
            }
        } else {
            $fldStr .= $fld;
        }

        return $fldStr;
    }

}