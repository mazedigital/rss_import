<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" >

	<guid/>

	<xsl:template match="/">
		<single>
			<xsl:for-each select='/*/item[guid=$guid]'>
				<xsl:variable name='description' select='description'/>
				<title>
					<xsl:value-of select='title'/>
				</title>
				<section>
					<xsl:value-of select='category'/>
				</section>
				<date>
					<xsl:value-of select='pubDate'/>
				</date>
				<description>
					<xsl:value-of select='description'/>
				</description>
				<content>
					<xsl:value-of select='content:encoded'/>
				</content>
				<author>
					<xsl:value-of select='dc:creator'/>
				</author>
			</xsl:for-each>
		</single>
	</xsl:template>

</xsl:stylesheet>
