#!/usr/bin/env python
# -*- coding: UTF-8 -*-

# $NoKeywords: $   for Visual Sourcesafe, stop replacing tags
__revision__ = "$Revision: 1.15 $"
__revision_number__ = __revision__.split()[1]
__version__ = "1.0.1b"
__date__ = "2004-08-01"
__url__ = "https://newspipe.sourceforge.net"
__author__ = "Ricardo M. Reyes <reyesric@ufasta.edu.ar>"
__contributors__ = ["Rui Carmo <http://the.taoofmac.com/space/>",]
__id__ = "$Id: newspipe.py,v 1.15 2004/08/02 16:52:04 reyesric Exp $"

ABOUT_NEWSPIPE = """
newspipe.py - version %s revision %s, Copyright (C) 2003-%s \n%s
"""%(__version__, __revision_number__, __date__.split('-')[0], __author__) 

#import psyco
#psyco.full()

import ConfigParser
import md5
from time import sleep
import os, sys, os.path
from cache import *
from datetime import datetime, timedelta
from pprint import pprint
from opml import *
from pickle import load, dump
import smtplib
import re
from htmlentitydefs import entitydefs
from difflib import SequenceMatcher
import email.Utils
import email.Header
import Queue
import threading
from htmlentitydefs import  *
import MimeWriter
import mimetools
import cStringIO
import base64
import urlparse
import traceback
import sys
import urllib
import logging
import logging.handlers
from urllib2 import URLError

has_html2text = True
try:
    from html2text import *
except ImportError:
    has_html2text = False

USER_AGENT = 'NewsPipe/'+__version__+' rev.'+__revision_number__+' '+__url__

def LogFile(stderr=True, name='default', location='.', debug=False):
    if not os.path.exists(location):
        os.makedirs(location)

    logger = logging.getLogger(name)
    hdlr = logging.handlers.RotatingFileHandler(os.path.join(location, name+'.log'), maxBytes=1024*500, backupCount=10)
    formatter = logging.Formatter('%(asctime)s %(levelname)-10s %(message)s')
    hdlr.setFormatter(formatter)
    logger.addHandler(hdlr) 

    if stderr:
        hdlr = logging.StreamHandler(sys.stderr)
        hdlr.setFormatter(formatter)
        logger.addHandler(hdlr)
    # end if

    if debug:
        logger.setLevel(logging.DEBUG)
    else:
        logger.setLevel(logging.INFO)
    # end if

    return logger
# end def    

def intEnt(m):
    m = int(m.groups(1)[0])
    return unichr(m).encode('latin1', 'ignore')

def xEnt(m):
    m = int(m.groups(1)[0], 16)
    return unichr(m).encode('latin1', 'ignore')

def expandEntities(text):
    text = text.replace("&lt;", "<")
    text = text.replace("&gt;", ">")
    text = text.replace("&quot;", '"')
    text = text.replace("&ob;", "{")
    text = text.replace("&cb;", "}")
    text = text.replace("&middot;", "*")
    text = re.sub("&[rl]squo;", "'", text)
    text = re.sub("&[rl]dquo;", '"', text)
    text = re.sub("&([aeiou])(grave|acute|circ|tilde|uml|ring);", lambda m: m.groups(1)[0], text)
    text = re.sub(r'&#(\d+);', intEnt, text)
    text = re.sub(r'&#[Xx](\w+);', xEnt, text)
    text = re.sub("&(#169|copy);", "(C)", text)
    text = re.sub("&mdash;", "--", text)
    text = re.sub("&amp;", "&", text)
    return text

class TextDiff:
    """Create diffs of text snippets."""

    def __init__(self, source, target):
        """source = source text - target = target text"""
        self.separadores = '"<>'
        self.nl = "<NL>"
        #self.delTag = "<span class='deleted'>%s</span>"
        self.delTag = '<font color="#FF0000"><STRIKE>%s</STRIKE></font>'
        #self.insTag = "<span class='inserted'>%s</span>"
        self.insTag = '<font color="#337700"><b>%s</b></font>'
        self.source = self.SplitHTML(source.replace("\n", "\n%s" % self.nl))
        self.target = self.SplitHTML(target.replace("\n", "\n%s" % self.nl))
        self.deleteCount, self.insertCount, self.replaceCount = 0, 0, 0
        self.diffText = None
        self.cruncher = SequenceMatcher(None, self.source, self.target)
        self._buildDiff()

    def SplitHTML (self, texto):
        version1 = re.compile('(<.+?>)').split(texto)

        version2 = []
        for x in version1:
            if re.compile('<.+>').search(x):
                version2 += [x,]
            else:
                version2 += x.split()
            # end if
        # end for
        return version2

    def _buildDiff(self):
        """Create a tagged diff."""
        outputList = []
        for tag, alo, ahi, blo, bhi in self.cruncher.get_opcodes():
            if tag == 'replace':
                # Text replaced = deletion + insertion
                outputList.append(self.delTag % " ".join(self.source[alo:ahi]))
                outputList.append(self.insTag % " ".join(self.target[blo:bhi]))
                self.replaceCount += 1
            elif tag == 'delete':
                # Text deleted
                outputList.append(self.delTag % " ".join(self.source[alo:ahi]))
                self.deleteCount += 1
            elif tag == 'insert':
                # Text inserted
                outputList.append(self.insTag % " ".join(self.target[blo:bhi]))
                self.insertCount += 1
            elif tag == 'equal':
                # No change
                outputList.append(" ".join(self.source[alo:ahi]))
        diffText = " ".join(outputList)
        diffText = " ".join(diffText.split())
        self.diffText = diffText.replace(self.nl, "\n")

    def getStats(self):
        "Return a tuple of stat values."
        return (self.insertCount, self.deleteCount, self.replaceCount)

    def getDiff(self):
        "Return the diff text."
        aux = self.diffText
        return aux

def createhtmlmail (html, text, headers, images=None, rss_feed=None, link=None):
    """Create a mime-message that will render HTML in popular
    MUAs, text in better ones"""

    global cache, log

    out = cStringIO.StringIO() # output buffer for our message
    htmlin = cStringIO.StringIO(html)
    txtin = cStringIO.StringIO(text)
    if rss_feed:
        rssin = cStringIO.StringIO(rss_feed)
    # end if

    writer = MimeWriter.MimeWriter(out)
    #
    # set up some basic headers... we put subject here
    # because smtplib.sendmail expects it to be in the
    # message body
    #

    for x,y in headers:
        writer.addheader(x, y.encode('latin1', 'replace'))

    writer.addheader("MIME-Version", "1.0")
    #
    # start the multipart section of the message
    # multipart/alternative seems to work better
    # on some MUAs than multipart/mixed
    #
    writer.startmultipartbody("related")
    writer.flushheaders()

    #
    # start the html subpart of the message
    #
    subpart = writer.nextpart()
    subpart.addheader("Content-Transfer-Encoding", "quoted-printable")
    #
    # returns us a file-ish object we can write to
    #
    pout = subpart.startbody("text/html", [("charset", 'utf8')])
    mimetools.encode(htmlin, pout, 'quoted-printable')
    htmlin.close()

    if images:
        for x in images:
            try:
                ext = 'gif'
                ruta, archivo = os.path.split(x['url'])
                if archivo:
                    name, ext = os.path.splitext(archivo)
                    if ext:
                        ext = ext[1:]

                        if '?' in ext:
                            ext = ext[:ext.find('?')]
                        # end if
                    # end if
                # end if

                content_type = "image/%s"%(ext)

                if link:
                    # if the url is relative, then add the link url to form an absolute address
                    url_parts = urlparse.urlsplit(x['url'])
                    if not url_parts[1]:
                        if not url_parts[0].upper() == 'FILE:':
                            x['url'] = urlparse.urljoin(link, x['url'])
                        # end if
                    # end if
                # end if
            
                retries = 0;
                MAX_RETRIES = 3;
                img_referer = link
                resource = None
                while retries < MAX_RETRIES:
                    retries += 1

                    # try to fetch the image.
                    # in case of Timeout or URLError exceptions, retry up to 3 times
                    try:
                        resource = cache.urlopen(x['url'], max_age=999999, referer=img_referer, can_pipe=False)
                    except HTTPError, e:
                        # in case of HTTP error 403 ("Forbiden") retry without the Referer
                        if e.code == 403 and img_referer:
                            log.info ('HTTP error 403 downloading %s, retrying withou the referer' % (x['url'],))
                            img_referer = None
                        else:
                            raise
                        # end if
                    except socket.timeout:
                        log.info ('Timeout error downloading %s' % (x['url'],))
                        if retries == MAX_RETRIES:
                            raise
                        # end if
                    except URLError, e:
                        log.info ('URLError (%s) downloading %s' % (e.reason, x['url'],))
                        if retries == MAX_RETRIES:
                            raise
                        # end if
                    except Exception:
                        raise # any other exception, kick it up, to be handled later
                    else:
                        # if there's no exception, break the loop to continue 
                        # processing the image
                        break
                    # end try
                            
                    log.info ('Retrying, %d time' % retries);
                # end while

                if not resource:
                    raise Exception('Unknown problem')
                # end if

                explicacion = resource.info['Cache-Result']

                log.debug (explicacion + ' ' + x['url'])

                info = resource.info
                if 'Content-Type' in info.keys():
                    content_type = info['Content-Type']
                # end if

                subpart = writer.nextpart()
                subpart.addheader("Content-Transfer-Encoding", "base64")
                subpart.addheader("Content-Disposition", "inline; filename=\"" +x['name'] + "\"" )
                subpart.addheader("Content-Id", x['name'])
                subpart.addheader("Content-Location", x['name'])
                f = subpart.startbody(content_type, [["name", x['name']]])
                b64 = base64.encodestring(resource.content.read())
                f.write(b64)

                image_ok = True  # the image was downloaded ok
            except KeyboardInterrupt:
                raise
            except socket.timeout:
                log.info ('Timeout error downloading %s' % (x['url'],))
                image_ok = False
            except HTTPError, e:
                log.info ('HTTP Error %d downloading %s' % (e.code, x['url'],))
                image_ok = False
            except URLError, e:
                log.info ('URLError (%s) downloading %s' % (e.reason, x['url'],))
                image_ok = False
            except OfflineError:
                log.info ('Resource unavailable when offline (%s)' % x['url'])
                image_ok = False
            except Exception, e:
                log.exception ('Error %s downloading %s' % (str(e), x['url'],))
                image_ok = False
            # end try
            if not image_ok:
                x['url'] = 'ERROR '+x['url'] # arruino la url para que no se reemplace en el html
            # end if
        # end for
    # end if

    #
    # the plain text section
    #

    #out.write(text)

    subpart = writer.nextpart()
    subpart.addheader("Content-Transfer-Encoding", "quoted-printable")
    pout = subpart.startbody("text/plain", [("charset", 'iso-8859-1'), ("delsp", 'yes'), ("format", 'flowed')])
    mimetools.encode(txtin, pout, 'quoted-printable')
    pout.write (txtin.read())
    txtin.close()

    if rss_feed:
        subpart = writer.nextpart()
        subpart.addheader("Content-Transfer-Encoding", "quoted-printable")
        pout = subpart.startbody("text/plain", [("charset", 'us-ascii'), ("Name", "rss_feed.xml")])
        mimetools.encode(rssin, pout, 'quoted-printable')
        rssin.close()
    # end if

    #
    # Now that we're done, close our writer and
    # return the message body
    #
    writer.lastpart()
    msg = out.getvalue()
    out.close()
    return msg

def createTextEmail(text, headers):
    t = '\r\n'.join([x+': '+y for x,y in headers])
    t += '\r\n\r\n'
    t += text
    return t
# end def    


def quitarEntitys (text):   
    return re.sub(r'(&\D+?;)', '', text)


class Channel:
    def __init__(self, title, original, xmlUrl, htmlUrl, download_link, diff):
        self.original = original
        self.xmlUrl = xmlUrl
        self.htmlUrl = htmlUrl
        self.title = original.get('title', title)
        self.description = original.get('description', self.title)
        self.creator = original.get('creator', original.get('author', self.title))
        self.download_link = download_link
        self.diff = diff

    def NewItem(self, original):
        return Item(original, self)
    # end def
# end class

def item_checksum(item):
    """ Calculates the MD5 checksum of an rss item """
    m = md5.new()
    for x in item.values():
        m.update (str(x))
    # end for
    return m.hexdigest()
# end def




historico_posts = {}
historico_feeds = {}


def getEntity(m):
    v = int(m.groups(1)[0])
    if v in codepoint2name.keys():
        return '&'+codepoint2name[v]+';'
    else:
        return ''
        #return '&#'+m.groups(1)[0]+';'
        #return unichr(v).encode('utf-8')

def SanitizeText (text):
    #text = text.replace('\n\n', '<br>')

    text = text.replace('\n', ' ')
    
    """
    text = text.replace('\xe2\x80\x99', "'")
    text = text.replace('\xe2\x80\x9c', '"')
    text = text.replace('\xe2\x80\x9d', '"')
    text = text.replace('\xe2\x80\x94', '-')
    text = text.replace('&#194;&#160;', '&nbsp;')
    """

    entitys = entitydefs
    inverso = {}
    for i,j in entitys.items():
        inverso[j] = '&'+i+';'

    chars = filter(lambda x: ord(x) >= 128, text)
    if chars:
        for c in chars:
            if inverso.has_key(c):
                text = text.replace(c, inverso[c])
            else:
                text = text.replace(c, '')
    

    text = re.sub(r'&#(\d+);', getEntity, text.encode('utf-8'))
    return text


def GetValue (x):
    if isinstance(x, basestring):
        return x
    elif isinstance(x, list):
        try:
            return x[0]['value']
        except:
            return ''
    else:
        return ''


entitydefs2 = {}
for key,value in entitydefs.items():
    entitydefs2[value] = key
# end for

def corregirEntitys(texto):
    if not texto:
        return texto
    # end if

    if isinstance(texto, unicode):
        texto = texto.encode('latin1', 'ignore')
    # end if
   
    result = ''
    for c in texto:
        if not (c in ('<', '>', '/', '"', "'", '=', '&')):
            if c in entitydefs2.keys():
                rep = entitydefs2[c]
                rep = '&'+rep+';'
                result += rep
            else:
                result += c
            # end if
        else:
            result += c
        # end if
    # end for

    if not isinstance(result, unicode):
        result = unicode(result, 'latin1', 'replace')
    # end if

    return result
# end def



def getException():
    return '\n'.join(traceback.format_exception (sys.exc_info()[0], sys.exc_info()[1], sys.exc_info()[2]))


semaforo_html2text = threading.BoundedSemaphore()


def makeHeader(text):
    if not isinstance(text, unicode):
        text = text.decode('latin1', 'replace')
    # end if

    if isinstance(text, unicode):
        text = text.encode('latin1', 'replace')
    # end if
    
    try:
        if has_html2text:
            text = html2text(text).strip()
    except UnicodeError:
        pass

    return str(email.Header.make_header([(text, 'latin1')]))
# end def    


def getPlainText(html, links=True):
    html_orig = html
    plain_text = u''
    if has_html2text:
        # html2text seems to be not-thread-safe, so I'm avoiding concurrency
        # here using a semaphore
        semaforo_html2text.acquire()
        try:
            try:
                if not isinstance(html, unicode):
                    html = html.decode('latin1', 'replace')
                # end if

                if isinstance(html, unicode):
                    html = html.encode('utf8', 'replace')
                # end if
                plain_text = html2text(html).strip()
            except:
                plain_text = getException ()
                log.exception ('Error en getPlainText')
            # end try
        finally:
            semaforo_html2text.release()
        # end try
    # end if

    if not isinstance(plain_text, unicode):
        plain_text = plain_text.decode('latin1')
    # end if

    return plain_text
# end def    


class Item:
    def __init__(self, original, channel):
        global historico_posts

        self.original = original
        self.link = GetValue(original.get('link', channel.htmlUrl))

        self.texto_nuevo = ''
        self.text_key = 'None'
        for k in 'content body content_encoded description summary symmary_detail'.split():
            if k in original.keys():
                if original[k]:
                    self.texto_nuevo = original[k]
                    self.text_key = k
                    break
                # end if
            # end if
        # end for

        self.texto_nuevo = GetValue (self.texto_nuevo)

        if channel.download_link:
            try:
                downloaded_file = cache.urlopen(self.link, max_age=999999, can_pipe=False);
                explicacion = downloaded_file.info['Cache-Result']
                log.debug (explicacion + ' ' + self.link)
            except KeyboardInterrupt:
                raise
            except:
                log.exception ('Cannot download '+self.link)
                downloaded_file = None
            # end try

            if downloaded_file:
                self.texto_nuevo = downloaded_file.content.read()
            # end if
        # end if

        self.texto_nuevo = corregirEntitys(self.texto_nuevo)

        try:
            self.subject = GetValue (original.get('title', ''))
        except UnicodeDecodeError:
            log.exception ('Unicode error '+channel.xmlUrl)
            self.subject = ''

        if not self.subject:
            sin_html = ' '.join(re.compile('<.+?>').split(self.texto_nuevo))

            self.subject = sin_html[:60].strip()

            if '\n' in self.subject:
                self.subject = self.subject.split('\n')[0]
            # end if
            self.subject += '...'
        # end if

        m = md5.new()
        m.update (self.link.encode('latin1', 'replace'))
        m.update (channel.xmlUrl.encode('latin1', 'replace'))
        m.update (self.subject.encode('latin1', 'replace'))
        self.urlHash = m.hexdigest()

        self.subject = self.subject

        if ('modified_parsed' in original.keys()) and (original['modified_parsed']):
            x = original['modified_parsed']
            self.timestamp = datetime(year=x[0], month=x[1], day=x[2], hour=x[3], minute=x[4], second=x[5])
        else:
            self.timestamp = datetime.now()
        # end if

        self.texto = self.texto_nuevo
        if channel.diff and historico_posts.has_key(self.urlHash):
            differ = TextDiff(historico_posts[self.urlHash]['text'], self.texto_nuevo)
            self.texto = differ.getDiff()
            self.timestamp = datetime.now()
        # end if

        self.channel = channel

        self.creatorName = GetValue(original.get('creator', original.get('author', channel.creator)))
        # set the default From: address to "rss@domain" where domain comes from the site's url
        self.creatorEmail = 'rss@'+ urlparse.urlparse(channel.htmlUrl)[1] 

        # search for an email address, in the item first, then in the channel
        r = re.compile('([A-Za-z0-9_.\+]+@[A-Za-z0-9_.]+)')
        for x in [original.get('creator', ''), original.get('author', ''), channel.creator]:
            try:
                m = r.search(x)
                if m:
                    self.creatorEmail = m.group(1)
            except TypeError:
                pass

    def __repr__(self):
        #return 'Link: %s\nTimeStamp: %s\nTexto: %s' % (self.link, self.timestamp, self.texto)
        return self.subject
        #return self.original.__repr__()
    # end def

    def GetEmail(self, envio, destinatario, plaintext=False):
        template = """
<FONT FACE="Arial,Helvetica,Geneva">
    <p>
        __body__
    </p>

    <p>
        <a href="__permalink__">
            Link
        </a>
        &nbsp;&nbsp;&nbsp;
        <a href="__htmlUrl__">
            Home
        </a>
    </p>
</FONT>
"""

        body = self.texto

        html_version = template
        html_version = html_version.replace('__body__', body)
        html_version = html_version.replace('__permalink__', self.link)
        html_version = html_version.replace('__htmlUrl__', self.channel.htmlUrl)

        text_version = getPlainText (html_version)

        if not plaintext:
            urls = re.findall(re.compile('<.*?img.+?src.*?=.*?[\'"](.*?)[\'"]', re.IGNORECASE), html_version)
            images = None
            if urls:
                images = []
                i = 0
                for url in urls:
                    if url:
                        ext = os.path.splitext(url)[1]
                        if '?' in ext:
                            ext = ext[:ext.find('?')]
                        # end if
                        name = 'image%d%s' % (i,ext)
                        html_version = html_version.replace('%s' % (url,), '%s' % (name,))
                        images += [{'name':name, 'url':url},]
                        i += 1
                    # end if
                # end for
            # end if
        # end if

        headers = []
        headers += [('From', '"%s" <%s>' % (makeHeader(self.channel.title), envio)),]
        headers += [('To', '"%s" <%s>' % (destinatario[0], destinatario[1],)),]
        headers += [('Subject', makeHeader(self.subject)),]
        headers += [('Message-ID', email.Utils.make_msgid()),]
        headers += [('Date', self.timestamp.strftime("%a, %d %b %Y %H:%M:%S +0000")),]

        headers += [('X-Item-Attributes', ', '.join(self.original.keys())),]
        headers += [('X-Item-Text-Key', self.text_key),]
        headers += [('X-NewsPipe-Version', '%s (Rev %s)' % (__version__, __revision_number__)),]
        headers += [('X-Channel-Feed', self.channel.xmlUrl),]
        headers += [('X-Channel-x-cache-result', self.channel.original['Cache-Result']),]
        headers += [('X-Channel-title', makeHeader(self.channel.title)),]
        headers += [('X-Channel-description', makeHeader(self.channel.description)),]

        if plaintext:
            return createTextEmail (text_version, headers).encode('latin1', 'replace')
        else:
            return createhtmlmail (html_version.encode('latin-1', 'replace'), text_version, headers, images, None, self.link)
        # end if
    # end def
# end class



def LeerConfig():
    ini = ConfigParser.ConfigParser()
    ini.read('./newspipe.ini')

    result = {}
    for attr in ini.options('NewsPipe'):
        result[attr.lower()] = ini.get('NewsPipe', attr)
    # end for
    return result
# end def


semaforo_email = threading.BoundedSemaphore()

def EnviarEmails(msgs, server):
    if msgs:
        semaforo_email.acquire()
        try:
            smtp = smtplib.SMTP(server)
            smtp.set_debuglevel(0)
            frompattern = re.compile(r'^From: .* <(.+)>', re.MULTILINE ) 
            topattern = re.compile(r'^To: .* <(.+)>', re.MULTILINE ) 

            for msg in msgs:
                # get initial "From:" and "To:" headers from message body to use in envelope
                # only way to have a generic queue for any sender/destination pair without
                # inserting objects in the queue (instead of just the message sources)
                head = msg[0:512]
                fromaddr = frompattern.search(head).group()
                toaddr = topattern.search(head).group()

                # build envelope and send message
                smtp.sendmail(fromaddr, toaddr, msg)
                log.debug('mail sent to %s from %s ' % (toaddr, fromaddr))
            # end for
            smtp.quit()
            log.info ('%d emails sent succesfully' % (len(msgs),))
        finally:
            semaforo_email.release()
    # end if
# end def

def AgruparItems(lista, titles):
    def cmpItems(x,y):
        if ('modified_parsed' in x.original.keys()) and (x.original['modified_parsed']):
            aux = x.original['modified_parsed']
            tsx = datetime(year=aux[0], month=aux[1], day=aux[2], hour=aux[3], minute=aux[4], second=aux[5])
        else:
            tsx = datetime.now()
        # end if

        if ('modified_parsed' in y.original.keys()) and (y.original['modified_parsed']):
            aux = y.original['modified_parsed']
            tsy = datetime(year=aux[0], month=aux[1], day=aux[2], hour=aux[3], minute=aux[4], second=aux[5])
        else:
            tsy = datetime.now()
        # end if

        return cmp(tsy,tsx)
    # end def    

    lista.sort (cmpItems)

    template1 = """
<FONT FACE="Arial,Helvetica,Geneva">
    <p>
        <font size=+1>
            <strong>
                <a href="__permalink__">
                    __subject__
                </a>
            </strong>
        </font>
        <br>
        <strong>
            by __creator__
        </strong>
        , __timestamp__
    </p>
    <p>
        __body__
    </p>
</FONT>
<hr>
"""

    template2 = """
<FONT FACE="Arial,Helvetica,Geneva">
    <p>
       <a href="__permalink__">#</a>&nbsp;
        __body__
    </p>
</FONT>
"""

    texto = ''

    for item in lista:
        if titles:
            html_version = template1
        else:
            html_version = template2
        # end if
        html_version = html_version.replace('__permalink__', item.link)
        html_version = html_version.replace('__subject__', item.subject)
        html_version = html_version.replace('__body__', item.texto)
        html_version = html_version.replace('__creator__', '<a href="mailto:%s">%s</a>' % (item.creatorEmail, item.creatorName))
        html_version = html_version.replace('__timestamp__', item.timestamp.strftime("%a, %d %b %Y %H:%M:%S +0000"))

        texto += html_version
    # end for
    dicc = {}
    dicc['body'] = texto
    dicc['title'] = '%s (%d items)' % (lista[0].channel.title, lista.__len__())
    dicc['link'] = lista[0].channel.htmlUrl
    if 'modified_parsed' in lista[0].original.keys():
        dicc['modified_parsed'] = lista[0].original['modified_parsed']
    # end if

    return lista[0].channel.NewItem(dicc)
# end def



def CargarHistoricos(name):
    data_dir = os.path.join(GetHomeDir(), '.newspipe/data')
    if not os.path.exists(data_dir):
        os.makedirs(data_dir)

    try:
        if historico_feeds:
            del(historico_feeds)
        # end if
    except UnboundLocalError: 
        pass

    try:
        file_name = os.path.join(data_dir, name+'.feeds')
        historico_feeds = load(open(file_name))
        log.debug('Cargando el archivo '+name+'.feeds')
    except:
        try:
            log.debug('No existe. Cargando el archivo '+name+'.feeds.bak')
            file_name = os.path.join(data_dir, name+'.feeds.bak')
            historico_feeds = load(open(file_name))
        except:
            historico_feeds = {}

    try:
        if historico_posts:
            del(historico_posts)
        # end if
    except UnboundLocalError: 
        pass

    try:
        file_name = os.path.join(data_dir, name+'.posts')
        log.debug('Cargando el archivo '+name+'.posts')
        historico_posts = load(open(file_name))
    except:
        try:
            log.debug('No existe. Cargando el archivo '+name+'.posts.bak')
            file_name = os.path.join(data_dir, name+'.posts.bak')
            historico_posts = load(open(file_name))
        except:
            historico_posts = {}

    historico_feeds['modified'] = False
    historico_posts['modified'] = False

    return historico_feeds, historico_posts
# end def


def GrabarHistorico(dicc, name, extension):
    data_dir = os.path.normpath(os.path.join(GetHomeDir(), '.newspipe/data'))
    
    log.debug('Grabando el archivo '+name+extension)
    dump(dicc, open(os.path.join(data_dir, name + extension +'.new'), 'w'))

    try: os.remove (os.path.join(data_dir, name+extension+'.bak'))
    except OSError: pass
    try: os.rename (os.path.join(data_dir, name+extension), os.path.join(data_dir, name+extension+'.bak'))
    except OSError: pass

    os.rename (os.path.join(data_dir, name+extension+'.new'), os.path.join(data_dir, name+extension))
    dicc['modified'] = False



def CheckOnline(config):
    if config.has_key('check_online'):
        url = config['check_online']
        try:
            log.debug ('Checking online status (downloading '+url+')')
            urllib.urlopen(url)
            log.debug ('Status: online')
            return True
        except:
            log.debug ('Status: offline')
            return False
    else:
        return True

def GetHomeDir():
    """ Returns the home directory of the current user."""
    
    for name in ('appdata', 'HOME'):
        result = os.environ.get(name, None)
        if result:
            return result
        # end if
    # end for

    # if it can't find the home directory trough environment vars, then 
    # return the path to this script.
    return os.path.split(sys.argv[0])[0]
# end def    

class FeedWorker (threading.Thread):
    def __init__(self, feeds_queue, email_queue, config, email_destino, movil_destino, semaforo):
        self.config = config
        self.email_destino = email_destino
        self.movil_destino = movil_destino
        self.semaforo = semaforo

        self.feeds_queue = feeds_queue
        self.email_queue = email_queue

        threading.Thread.__init__(self)
    # end def

    def run(self):
        config = self.config
        email_destino = self.email_destino
        movil_destino = self.movil_destino
        semaforo = self.semaforo

        while True:
            feed = self.feeds_queue.get()
            if feed is None:
                break
            # end if

            url = feed['xmlUrl']

            try:
                items = []

                semaforo.acquire()
                if not historico_feeds.has_key(url):
                    historico_feeds[url] = {}
                    historico_feeds[url]['ultimo_check'] = None
                    historico_feeds[url]['proximo_check'] = None
                    historico_feeds[url]['ultima_actualizacion'] = None
                    historico_feeds[url]['delay'] = None
                    historico_feeds['modified'] = True
                # end if
                semaforo.release()

                ultimo_check           = historico_feeds[url]['ultimo_check']
                proximo_check          = historico_feeds[url]['proximo_check']
                ultima_actualizacion   = historico_feeds[url].get('ultima_actualizacion', None)
                delay                  = historico_feeds[url].get('delay', None)

                ahora = datetime.now()
                if proximo_check and ahora < proximo_check:
                    continue
                # end if

                title = feed.get('title', feed.get('text', url))
                log.debug ('Procesando '+title)
                xml = None
                try:
                    xml = cache.feed_parse(url, can_pipe=config.get('can_pipe', '0') == '1')
                except socket.timeout:
                    log.info ('Timeout error downloading %s' % url)
                    log.debug ('Will retry in the the next pass')
                    continue
                except HTTPError, e:
                    log.info ('HTTP Error %d downloading %s' % (e.code, url,))
                except URLError, e:
                    log.info ('URLError (%s) downloading %s' % (e.reason, url,))
                except OfflineError:
                    log.info ('Resource unavailable when offline (%s)' % url)
                except Exception, e:
                    log.exception ('Error %s downloading %s' % (str(e), url))

                if xml:
                    log.debug (xml['channel']['Cache-Result'] + ' ' + url)
                    channel = Channel(title, xml['channel'], url, feed['htmlUrl'], feed.get('download_link', '0') == '1', feed.get('diff', '1') == '1')
                    for elemento in xml['items']:
                        item = channel.NewItem(elemento)

                        if historico_posts.has_key(item.urlHash):
                            historico_posts[item.urlHash]['timestamp'] = datetime.now()
                            historico_posts['modified'] = True

                            check_text = feed.get('check_text', '1') == '1'

                            if check_text:
                                if item.texto_nuevo.strip() == historico_posts[item.urlHash]['text'].strip():
                                    continue
                                # end if
                            else:
                                continue
                            # end if
                        # end if

                        items.append(item)
                    # end for
                # end if xml:

                if items:
                    log.info ('%d new items in %s' % (items.__len__(),title))
                else:
                    log.debug ('No change in %s' % (title,))
                # end if

                items_sin_agrupar = items[:]

                if (len(items) >= 1) and (feed.get('digest', '0') == '1'):
                    lista_vieja = items[:]
                    items = [AgruparItems(lista_vieja, feed.get('titles', '1') == '1'),]
                # end if

                email_ok = True
                envio = config.get( 'sender', email_destino[1] )
                plaintext = (config.get('textonly', '0') == '1') or (feed.get('textonly', '0') == '1')
                if config.get('send_immediate', '0') == '1':
                    try:
                        emails = [item.GetEmail(envio, email_destino, plaintext) for item in items]
                        EnviarEmails (emails, config['smtp_server'])
                    except Exception, e:
                        email_ok = False
                        log.exception ('Error enviando los emails: %s' % (str(e),))
                    # end try
                else:
                    for item in items:
                        self.email_queue.put(item.GetEmail(envio, email_destino, plaintext))
                    # end for
                # end if

                # second pass for mobile copy, provided we could send the first one
                if( (feed.get('mobile','0') == '1' ) and movil_destino and email_ok ):
                   plaintext = True
                   if config.get('send_immediate', '0') == '1':
                      try:
                          emails = [item.GetEmail(envio, movil_destino, plaintext) for item in items]
                          EnviarEmails (emails, config['smtp_server'])
                      except Exception, e:
                          email_ok = False
                          log.exception ('Error enviando los emails: %s' % (str(e),))
                      # end try
                   else:
                      for item in items:
                          self.email_queue.put(item.GetEmail(envio, movil_destino, plaintext))
                      # end for
                  # end if

                if email_ok:
                    for item in items_sin_agrupar:
                        historico_posts[item.urlHash] = {'text':item.texto_nuevo, 'timestamp':datetime.now()}
                        historico_posts['modified'] = True
                    # end for

                    # get the time until next check, 60 minutos by default
                    delay = int(feed.get('delay', '60'))

                    ###semaforo.acquire()
                    historico_feeds[url]['ultimo_check'] = datetime.now()
                    historico_feeds[url]['proximo_check'] = datetime.now() + timedelta(minutes=delay)
                    if items.__len__() > 0:
                        historico_feeds[url]['ultima_actualizacion'] = datetime.now()
                    historico_feeds[url]['delay'] = delay
                    historico_feeds['modified'] = True
                    ###semaforo.release()
                # end if
            except:
                log.exception ('Exception processing '+url)
        # end while
    # end def
# end class

log = None

def MainLoop():
    global historico_posts
    global historico_feeds
    global cache
    global log

    semaforo = threading.BoundedSemaphore()
    historico_feeds, historico_posts = None, None

    while True:
        config = LeerConfig()

        DEBUG = config.get('debug', '0') == '1'
    
        if not log:
            log_dir = os.path.normpath(os.path.join(GetHomeDir(), '.newspipe/log'))
            log = LogFile(config.get('log_console', '0')  == '1', 'newspipe', log_dir, DEBUG)        
        # end if

        if DEBUG:
            log.warning ('DEBUG MODE')
        # end if

        log.debug ('Home directory: '+GetHomeDir())

        try:
            log.debug ('Parametros de configuración')
            log.debug ('-'*30)
            for x,y in config.items():
                log.debug ('%s: %s', x, y)
            # end for
            log.debug ('-'*30)

            cache.offline = config.get('offline', '0') == '1'
            if cache.offline:
                log.warning('Working offline')
            # end if

            cache.debug = DEBUG

            if CheckOnline(config):
                NUM_WORKERS = int(config.get('workers', '10'))

                archivo = config['opml']

                opml = None
                try:
                    opml = AplanarArbol(ParseOPML(cache.urlopen(archivo, max_age=60, can_pipe=False).content))
                    log.debug ('Procesing file: '+archivo)
                except:
                    log.exception ('Error parsing file: '+archivo)
                    opml = None

                if opml:
                    email_destino = (opml['head']['ownerName'].strip('"'), opml['head']['ownerEmail'])
                    if( opml['head'].has_key('ownerMobile') ):
                        movil_destino = (opml['head']['ownerName'].strip('"'), opml['head']['ownerMobile'])
                    else:
                        movil_destino = False

                    if not historico_feeds or not historico_posts:
                        historico_feeds, historico_posts = CargarHistoricos(opml['head']['title'])

                    feeds_queue = Queue.Queue(0)
                    email_queue = Queue.Queue(0)

                    workers = []
                    for x in range(NUM_WORKERS):
                        w = FeedWorker (feeds_queue, email_queue, config, email_destino, movil_destino, semaforo)
                        workers.append(w)
                        w.start()
                    # end for
                    
                    for feed in opml['body']:
                        feeds_queue.put(feed)
                    # end for

                    for x in range(NUM_WORKERS):
                        feeds_queue.put(None)
                    # end for

                    for w in workers:
                        w.join()
                    # end for                

                    emails = []
                    while True:
                        try:
                            email = email_queue.get_nowait()
                            emails += [email,]
                        except Queue.Empty:
                            break
                        # end try
                    # end while

                    try:
                        EnviarEmails (emails, config['smtp_server'])
                        if historico_feeds['modified']:
                            GrabarHistorico (historico_feeds, opml['head']['title'], '.feeds')
                        if historico_posts['modified']:
                            GrabarHistorico (historico_posts, opml['head']['title'], '.posts')
                    except KeyboardInterrupt:
                        raise
                    except Exception, e:
                        log.exception ('Error enviando los emails: %s' % (str(e),))
                    # end try

                    log.debug (archivo + ' terminado')

                    # borrar las entradas del historico que son demasiado viejas
                    historico_feeds, historico_posts = CargarHistoricos(opml['head']['title'])
                    for hash, value in historico_posts.items():
                        if hash == 'modified':
                            continue
                        timestamp = value['timestamp']
                        delta = timedelta(days = 45) # borrar lo que tenga mas 45 dias de antiguedad - maybe this should be configurable too
                        if (datetime.now() - delta) > timestamp:
                            del historico_posts[hash]
                            historico_posts['modified'] = True
                        # end if
                    # end for
                    if historico_posts['modified']:
                        GrabarHistorico (historico_posts, opml['head']['title'], '.posts')
                # end if
            # end if CheckOnline

            # erase from the cache anything older than 10 days - to be made configurable?
            cache.purge(10)

            if int(config.get('sleep_time', '0')) == 0:
                break
            else:
                del(historico_feeds)
                del(historico_posts)
                historico_feeds, historico_posts = None, None

                log.debug ('Going to sleep for %s minutes' % (config['sleep_time'],))
                for sec in range(int(config['sleep_time'])*60):
                    sleep(1)
                # end for
            # end if
        except:
            log.exception ('Unhandled exception')
            raise  # stop the loop, to avoid infinite exceptions loops ;)
    # end while
# end def



if __name__ == '__main__':
    print ABOUT_NEWSPIPE

    log = None

    cache_dir = os.path.normpath(os.path.join(GetHomeDir(), '.newspipe/cache'))
    cache = Cache(cache_dir, agent=USER_AGENT)
    try:
        MainLoop()
    except KeyboardInterrupt:
        pass
