-- Filtro pandoc: tabelle a tutta larghezza con colonne proporzionate al contenuto,
-- intestazioni in grassetto e codice in linea con uno stile di carattere dedicato.
local function lunghezza(cella)
  return utf8.len(pandoc.utils.stringify(cella.contents)) or 0
end

function Table(t)
  local n = #t.colspecs
  local pesi = {}
  for i = 1, n do pesi[i] = 4 end
  local function esamina(righe)
    for _, riga in ipairs(righe) do
      for i, cella in ipairs(riga.cells) do
        if i <= n then
          pesi[i] = math.max(pesi[i], math.min(lunghezza(cella), 46))
          -- una colonna non deve mai spezzare la sua parola più lunga
          for parola in pandoc.utils.stringify(cella.contents):gmatch("%S+") do
            pesi[i] = math.max(pesi[i], math.min((utf8.len(parola) or 0) * 1.7, 46))
          end
        end
      end
    end
  end
  esamina(t.head.rows)
  for _, corpo in ipairs(t.bodies) do esamina(corpo.body) end
  local totale = 0
  for i = 1, n do pesi[i] = pesi[i] + 3; totale = totale + pesi[i] end
  for i = 1, n do t.colspecs[i][2] = pesi[i] / totale end
  for _, riga in ipairs(t.head.rows) do
    for _, cella in ipairs(riga.cells) do
      cella.contents = pandoc.walk_block(pandoc.Div(cella.contents), {
        Plain = function(p) return pandoc.Plain({ pandoc.Strong(p.content) }) end,
      }).content
    end
  end
  return t
end

function Code(c)
  return pandoc.Span({ pandoc.Str(c.text) }, pandoc.Attr("", {}, { ["custom-style"] = "CodiceInLinea" }))
end

-- Il PHP viene colorato solo se inizia con il tag di apertura: lo si aggiunge qui
-- e build/pulisci.py lo toglie dal documento finale.
function CodeBlock(b)
  if b.classes:includes("php") and not b.text:match("^<%?php") then
    b.text = "<?php\n" .. b.text
    return b
  end
end
