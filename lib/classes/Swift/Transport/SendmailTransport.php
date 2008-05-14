<?php

/*
 Sendmail Transport from Swift Mailer.
 
 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.
 
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
 
 */

//@require 'Swift/Transport/EsmtpTransport.php';
//@require 'Swift/Transport/IoBuffer.php';
//@require 'Swift/Transport/Log.php';
//@require 'Swift/Events/EventDispatcher.php';

/**
 * SendmailTransport for sending mail through a sendmail/postfix (etc..) binary.
 * @package Swift
 * @subpackage Transport
 * @author Chris Corbyn
 */
class Swift_Transport_SendmailTransport extends Swift_Transport_AbstractSmtpTransport
{
  
  /**
   * Connection buffer parameters.
   * @var array
   * @access protected
   */
  private $_params = array(
    'timeout' => 30,
    'blocking' => 1,
    'command' => '/usr/sbin/sendmail -bs',
    'type' => Swift_Transport_IoBuffer::TYPE_PROCESS
    );
  
  /**
   * Create a new SendmailTransport with $buf for I/O.
   * @param Swift_Transport_IoBuffer $buf
   * @param Swift_Events_EventDispatcher $dispatcher
   */
  public function __construct(Swift_Transport_IoBuffer $buf,
    Swift_Events_EventDispatcher $dispatcher)
  {
    parent::__construct($buf, $dispatcher);
  }
  
  /**
   * Start the standalone SMTP session if running in -bs mode.
   */
  public function start()
  {
    if (false !== strpos($this->getCommand(), ' -bs'))
    {
      parent::start();
    }
  }
  
  /**
   * Set the command to invoke.
   * If using -t mode you are strongly advised to include -oi or -i in the
   * flags. For example: /usr/sbin/sendmail -oi -t
   * Swift will append a -f<sender> flag if one is not present.
   * The recommended mode is "-bs" since it is interactive and failure notifications
   * are hence possible.
   * @param string $command
   */
  public function setCommand($command)
  {
    $this->_params['command'] = $command;
    return $this;
  }
  
  /**
   * Get the sendmail command which will be invoked.
   * @return string
   */
  public function getCommand()
  {
    return $this->_params['command'];
  }
  
  /**
   * Send the given Message.
   * Recipient/sender data will be retreived from the Message API.
   * The return value is the number of recipients who were accepted for delivery.
   * NOTE: If using 'sendmail -t' you will not be aware of any failures until
   * they bounce (i.e. send() will always return 100% success).
   * @param Swift_Mime_Message $message
   * @param string[] &$failedRecipients to collect failures by-reference
   * @return int
   */
  public function send(Swift_Mime_Message $message, &$failedRecipients = null)
  {
    $failedRecipients = (array) $failedRecipients;
    $command = $this->getCommand();
    $buffer = $this->getBuffer();
    if (false !== strpos($command, ' -t'))
    {
      if (false === strpos($command, ' -f'))
      {
        $command .= ' -f' . $this->_getReversePath($message);
      }
      $buffer->initialize(array_merge($this->_params, array('command' => $command)));
      $buffer->setWriteTranslations(array("\r\n"=>"\n"));
      $count = count((array) $message->getTo())
        + count((array) $message->getCc())
        + count((array) $message->getBcc())
        ;
      $message->toByteStream($buffer);
      $buffer->setWriteTranslations(array());
      $buffer->terminate();
    }
    elseif (false !== strpos($command, ' -bs'))
    {
      $count = parent::send($message, $failedRecipients);
    }
    else
    {
      $this->_throwException(new Swift_Transport_TransportException(
        'Unsupported sendmail command flags [' . $command . ']. ' .
        'Must be one of "-bs" or "-t" but can include additional flags.'
        ));
    }
    
    return $count;
  }
  
  // -- Protected methods
  
  /** Get the params to initialize the buffer */
  protected function _getBufferParams()
  {
    return $this->_params;
  }
  
}