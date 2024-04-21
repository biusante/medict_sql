<?xml version="1.0" encoding="UTF-8"?>
<xsl:transform version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns="http://www.w3.org/1999/xhtml" xmlns:tei="http://www.tei-c.org/ns/1.0" exclude-result-prefixes="tei">
  <!-- 
Ce parseur est destiné a un dictionnaire XML/TEI pour la BIUSanté
https://github.com/biusante/medict-xml
Il traverse tous les éléments et retient des informations méritant d’être insérées dans une base SQL,
sous la forme d’une ligne tsv.
Cette étape intermédiaire pemet le cas échéant de vérifier ce qui est extrait.
Un parseur (php en l’occurrence) traversera toutes ces lignes en ordre séquentiel,
en retenant les événements utiles en cours de contexte, notamment, les sauts de page,
permettant de raccrocher chaque information lexicale à sa page source.
  -->
  <xsl:output method="text" encoding="UTF-8"/>
  <xsl:variable name="lf">
    <xsl:text>&#10;</xsl:text>
  </xsl:variable>
  <xsl:variable name="tab">
    <xsl:text>&#9;</xsl:text>
  </xsl:variable>
  
  <xsl:template match="tei:teiHeader">
    <!--
<ref target="https://www.biusante.parisdescartes.fr/histoire/medica/resultats/index.php?do=chapitre&amp;cote=00152">De Gorris 1601</ref>
ref	De Gorris 1601		
    -->
  </xsl:template>

  <xsl:template match="/">
    <xsl:text>object</xsl:text>
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="$lf"/>
    <xsl:apply-templates/>
  </xsl:template>

  <xsl:template match="text()"/>



  <xsl:template match="*">
    <xsl:apply-templates/>
  </xsl:template>
  
  <xsl:template match="tei:TEI">
    <xsl:text>volume</xsl:text>
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="@n"/>
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="@ana"/>
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:date/@when"/>
    <xsl:value-of select="$lf"/>
    <xsl:apply-templates/>
  </xsl:template>
  

  <xsl:template match="tei:pb">
    <xsl:value-of select="local-name()"/>
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="@n"/>
    <xsl:value-of select="$tab"/>
    <!-- refimg -->
    <xsl:value-of select="substring-before(substring-after(substring-after(@facs, 'iiif/2/bibnum:'), ':'), '/')"/>
    <!--
    <xsl:value-of select="@facs"/>
    <xsl:value-of select="@corresp"/>
    -->
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="$lf"/>
    <xsl:apply-templates/>
  </xsl:template>

  <xsl:template match="tei:entry | tei:entryFree" name="entry">
    <xsl:text>entry</xsl:text>
    <xsl:value-of select="$tab"/>
    <!-- Vedette -->
    <xsl:for-each select=".//tei:orth">
      <xsl:if test="position() != 1">, </xsl:if>
      <xsl:value-of select="."/>
    </xsl:for-each>
    <!-- pps -->
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="count(.//tei:pb)"/>
    <xsl:value-of select="$tab"/>
    <!--
    <xsl:value-of select="preceding::tei:pb[1]/@n"/>
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="(.//tei:pb)[position() = last()]/@n"/>
    -->
    <xsl:value-of select="$lf"/>
    <xsl:apply-templates/>
    <!-- Non, redondant
    <xsl:text>/entry</xsl:text>
    <xsl:value-of select="$lf"/>
    -->
  </xsl:template>
  


  <xsl:template match="tei:orth">
    <xsl:value-of select="local-name()"/>
    <xsl:value-of select="$tab"/>
    <xsl:variable name="txt">
      <xsl:apply-templates mode="copy"/>
    </xsl:variable>
    <xsl:value-of select="normalize-space($txt)"/>
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="@xml:lang"/>
    <xsl:value-of select="$tab"/>
    <xsl:value-of select="$lf"/>
  </xsl:template>

  <xsl:template match="tei:sense[.//tei:term] | tei:p[.//tei:term]">
    <xsl:for-each select=".//tei:term">
      <xsl:text>term</xsl:text>
      <xsl:value-of select="$tab"/>
      <xsl:variable name="txt">
        <xsl:apply-templates mode="copy"/>
      </xsl:variable>
      <xsl:value-of select="normalize-space($txt)"/>
      <xsl:value-of select="$tab"/>
      <xsl:value-of select="$tab"/>
      <xsl:value-of select="$lf"/>
    </xsl:for-each>
    <xsl:choose>
      <!-- pas de suggestion si Littré-Gilbert 1907 -->
      <xsl:when test="ancestor::tei:entry[@corresp='medict37020d.xml']"/>
      <xsl:otherwise>
        <xsl:text>clique</xsl:text>
        <xsl:value-of select="$tab"/>
        <xsl:for-each select=".//tei:term">
          <xsl:if test="position() != 1"> | </xsl:if>
          <xsl:variable name="txt">
            <xsl:apply-templates mode="copy"/>
          </xsl:variable>
          <xsl:value-of select="normalize-space($txt)"/>
        </xsl:for-each>
        <xsl:for-each select=".//tei:ref">
          <xsl:text> | </xsl:text>
          <xsl:variable name="txt">
            <xsl:apply-templates mode="copy"/>
          </xsl:variable>
          <xsl:value-of select="normalize-space($txt)"/>
        </xsl:for-each>
        <xsl:for-each select=".//tei:xr">
          <xsl:text> | </xsl:text>
          <xsl:variable name="txt">
            <xsl:apply-templates mode="copy"/>
          </xsl:variable>
          <xsl:value-of select="normalize-space($txt)"/>
        </xsl:for-each>
        <xsl:value-of select="$tab"/>
        <xsl:value-of select="$tab"/>
        <xsl:value-of select="$lf"/>
      </xsl:otherwise>
    </xsl:choose>
    <xsl:apply-templates select=".//tei:pb"/>
  </xsl:template>
  
  <xsl:template match="tei:choice" mode="copy">
    <xsl:choose>
      <xsl:when test="tei:corr">
        <xsl:apply-templates select="tei:corr/node()" mode="copy"/>
      </xsl:when>
      <xsl:when test="tei:reg">
        <xsl:apply-templates select="tei:reg/node()" mode="copy"/>
      </xsl:when>
      <xsl:when test="tei:expan">
        <xsl:apply-templates select="tei:expan/node()" mode="copy"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:apply-templates/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <xsl:template match="tei:ref | tei:xr">
    <xsl:choose>
      <!-- pas de suggestion si Littré-Gilbert 1907 -->
      <xsl:when test="ancestor::tei:entry[@corresp='medict37020d.xml']"/>
      <xsl:otherwise>
        <xsl:text>clique</xsl:text>
        <xsl:value-of select="$tab"/>
        <xsl:variable name="clique">
          <xsl:apply-templates mode="copy"/>
        </xsl:variable>
        <xsl:value-of select="normalize-space($clique)"/>
        <xsl:for-each select="tei:ref">
          <xsl:text> | </xsl:text>
          <xsl:variable name="txt">
            <xsl:apply-templates mode="copy"/>
          </xsl:variable>
          <xsl:value-of select="normalize-space($txt)"/>
        </xsl:for-each>
        <xsl:value-of select="$tab"/>
        <xsl:value-of select="$tab"/>
        <xsl:value-of select="$lf"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <xsl:template match="tei:foreign">
    <xsl:choose>
      <!-- su comme non sûr -->
      <xsl:when test="@cert and @cert = 'low'"/>
      <!-- pas de suggestion si Littré-Gilbert 1907 -->
      <xsl:when test="ancestor::tei:entry[@corresp='medict37020d.xml']"/>
      <xsl:otherwise>
        <xsl:value-of select="local-name()"/>
        <xsl:value-of select="$tab"/>
        <xsl:variable name="txt">
          <xsl:apply-templates mode="copy"/>
        </xsl:variable>
        <xsl:value-of select="normalize-space($txt)"/>
        <xsl:value-of select="$tab"/>
        <xsl:value-of select="@xml:lang"/>
        <xsl:value-of select="$tab"/>
        <xsl:value-of select="$lf"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <xsl:template match="tei:sense//tei:foreign">
    <!-- Non vérifié, ne rien sortir pour l’instant -->
  </xsl:template>


</xsl:transform>