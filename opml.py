#!/usr/bin/env python
# -*- coding: UTF-8 -*-

# $NoKeywords: $   for Visual Sourcesafe, stop replacing tags
__revision__ = "$Revision: 1.3 $"
__revision_number__ = __revision__.split()[1]
__url__ = "https://newspipe.sourceforge.net"
__author__ = "Ricardo M. Reyes <reyesric@ufasta.edu.ar>"
__id__ = "$Id: opml.py,v 1.3 2004/12/08 20:25:33 reyesric Exp $"

from pprint import pprint

def getText(nodelist):
    rc = ""
    for node in nodelist:
        if node.nodeType == node.TEXT_NODE:
            rc = rc + node.data
    return rc

def CrearDiccionario(raiz):
    result = {}

    if raiz.getElementsByTagName ('outline'):
        outline = True
    else:
        outline = False
    # end if

    for attr, value in raiz.attributes.items():
        result[attr] = value
    # end for

    if outline:
        result[u'childs'] = {}
        for hijo in [x for x in raiz.childNodes if x.nodeName == 'outline']:
            nombre = hijo.attributes.get('title', hijo.attributes.get('text')).value
            if nombre in result[u'childs'].keys():
                i = 1
                original = nombre
                nombre = original + str(i)
                while nombre in result[u'childs'].keys():
                    i += 1
                    nombre = original + str(i)
                # end while
            # end if
            result[u'childs'][nombre] = CrearDiccionario(hijo)
        # end for
    else:
        for node in raiz.childNodes:
            result[node.nodeName] = getText(node.childNodes)
        # end for
    # end if

    return result
# end def    

def ParseOPML(archivo):
    import xml.dom.minidom

    result = {}

    dom = xml.dom.minidom.parse(archivo)

    node = dom.getElementsByTagName('opml')[0]
    result[u'opml'] = {u'head':CrearDiccionario(node.getElementsByTagName('head')[0]), 
                       u'body':CrearDiccionario(node.getElementsByTagName('body')[0])}

    #result = CrearDiccionario(dom)

    dom.unlink()

    return result
# end def    

def ProcesarRama(rama, resultados, antecesores, valores_heredados):
    valores = {}
    for key in rama.keys():
        if key != 'childs':
            valores[key] = rama[key]
        # end if        
    # end for

    for attr, value in valores_heredados.items():
        if not attr in valores.keys():
            valores[attr] = value
        # end if
    # end for

    if 'childs' in rama.keys():
        hijos = rama['childs']
        for hijo in hijos.keys():
            ProcesarRama (hijos[hijo], resultados, antecesores + [hijo,], valores)
        # end for
    else:
        if antecesores.__len__() > 1:
            valores[u'path'] = '/' + u'/'.join(antecesores[:-1])
        else:
            valores[u'path'] = '/'
        # end if
        resultados += [valores,]
    # end if
# end def    

def ListToDict(lista):
    result = {}

    for attr, value in lista:
        result[attr] = value
    # end for

    return result
# end def    

def AplanarArbol(arbol, defaults=None):
    lista = []

    ProcesarRama(arbol['opml']['body'], lista, [], {})

    result = {'head':ListToDict(arbol['opml']['head'].items()),
              'body':lista}
            
    # add an index value to each item            
    for i, each in enumerate(lista):
        each[u'index'] = unicode(str(i))
                  
    # add the default values to those item that are not complete
    if defaults:
        for each in lista:
            for key,value in defaults.items():
                if not isinstance(key, unicode):
                    key = unicode(key)
                if not isinstance(value, unicode):
                    value = unicode(value)
                if not key in each.keys():
                    each[key] = value

    return result
# end def    

if __name__ == '__main__':
    pprint (AplanarArbol(ParseOPML('prueba.opml'), {'key1':'val1', 'key2':'val2'}))
