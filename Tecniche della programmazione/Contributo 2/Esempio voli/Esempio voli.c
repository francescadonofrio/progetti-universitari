/* Scelta da menu compiuta dall'utente per:
   inserimento di un volo ;
   lettura dei voli da file
   modifica di un volo (orario, nome del comandante, prenotazione/disdetta posti)
   visualizzazione di un volo
   salvataggio dei voli su file
   cancellazione di un volo 
   operazioni di gestione dei voli
						-       aggiungere un volo
						-       stampare tutti i voli
						-       modificare un volo
*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>


struct volo
{
   char codice[6];
   char *destinazione;
   char *comandante;
   int ora;
   int minuti;
   int postiLiberi;
   int postiTotali;
};

typedef struct volo tVolo;

struct nodoLista
{
   tVolo unVolo;
   struct nodoLista *nextVolo;
};

typedef struct nodoLista tNodo;

typedef tNodo *tLista;

void leggiVoli(tLista *, char *);
void insTestaLista(tLista *, tVolo *);
void leggiVolo(tVolo *);
void stampaLista (tLista);
void stampaNodo(tNodo *);
void modificaVolo(tLista );
void cancellaVolo(tLista *);
void memorizzaVoli(tLista, char *);

int main()
{
   /*E` indispensabile l'inizializzaione di questa variabile a NULL,
     per mantenere l'indicatore di fine lista
	*/
   tLista unaLista = NULL;
   tVolo voloAppoggio;
   int scelta;

   do
   {
      printf(  "menu:\n"
               "1 -- inserisci un volo\n"
               "2 -- stampa lista voli\n"
               "3 -- modifica un volo\n"
               "4 -- leggi i voli\n"
               "5 -- memorizza i voli\n"
               "6 -- cancella un volo\n"
               "0 -- esci\n");
      scanf("%d", &scelta);
      switch(scelta)
      {
         case 1:
            leggiVolo(&voloAppoggio);
            insTestaLista(&unaLista, &voloAppoggio);
            break;
         case 2:
            stampaLista(unaLista);
            break;
         case 3:
            modificaVolo(unaLista);
            break;
         case 4:
            leggiVoli(&unaLista, "voli.txt");
            break;
         case 5:
            memorizzaVoli(unaLista, "voli.txt");
            break;
         case 6:
            cancellaVolo(&unaLista);
            break;
         default:
            break;
      }
   }while (scelta);
return 0;
}


void leggiVolo(tVolo *pVolo)
{
   char buffer[100];

   printf("inserisci il codice del volo: ");
   scanf("%s", pVolo->codice);
   printf("inserisci la destinazione del volo: ");
   scanf("%s", buffer);
   /* allocazione dinamica di tanti bytes quanto sono necessari:
      utilizzo dell'operatore ->: pVolo->destinazione e` equivalente a (*pVolo).destinazione
	 */
   pVolo->destinazione = malloc(sizeof(strlen(buffer)+1) );
   strcpy(pVolo->destinazione, buffer);

   /* unica modifica necessaria nella fase di acquisizione dati
      risulta LOCALIZZATA se le funzioni sono state realizzate con
      l' accortezza di essere indipendenti dall'informazione
	 */
   printf("inserisci il nome del comandante del volo: ");
   scanf("%s", buffer);
   pVolo->comandante = malloc(sizeof(strlen(buffer)+1) );
   strcpy(pVolo->comandante, buffer);

   printf("inserisci l'orario del volo: ");
   scanf("%d %d", &(pVolo->ora), &(pVolo->minuti));
   printf("inserisci i posti totali del volo: ");
   scanf("%d", &(pVolo->postiTotali));

   printf("inserisci i posti liberi del volo: ");
   scanf("%d", &(pVolo->postiLiberi));
}

void insTestaLista(tLista *pInizioLista, tVolo *pNuovoVolo) {
   tLista pNuovoNodo;

   pNuovoNodo = malloc(sizeof(tNodo));

   if (!pNuovoNodo)  
      printf("\nimpossibile allocare memoria a sufficienza!\n");
   else
   {
      /* copia i valori puntati da pNuovoVolo nello spazio del volo appena allocato.
         Non fa nessuna copia parziale, ma una copia 'brutale'
		*/
      pNuovoNodo->unVolo = *pNuovoVolo;
      
	  /*l'aggancio di un nuovo nodo avviene all' "inizio" o "sopra"*/
      pNuovoNodo->nextVolo = *pInizioLista;

      /* con questa variazione il puntatore ad inizio lista si aggiorna, 
	     anche nella funzione chiamante	*/
      *pInizioLista = pNuovoNodo;
   }
}

void stampaLista (tLista laLista)
{
   printf("lista dei voli in ordine inverso rispetto all'inserimento: \n");
   printf("%-6s\t%-12s\t%-15s\t%-5s\t%-3s\t%-3s\n",
           "volo", "destinazione", "comandante", "orario", "PT", "posti liberi");
   while (laLista)
   {
      stampaNodo(laLista);
      laLista = laLista->nextVolo;
   }
}

void stampaNodo(tNodo *pNodo) {
   printf("%-6s\t%-12s\t%-15s\t%d:%d\t%d\t%d\n",
              pNodo->unVolo.codice,
              pNodo->unVolo.destinazione,
              pNodo->unVolo.comandante,
              pNodo->unVolo.ora,
              pNodo->unVolo.minuti,
              pNodo->unVolo.postiTotali,
              pNodo->unVolo.postiLiberi);
}

void modificaVolo(tLista laLista) {
   char codiceCercato[6], buffer[30];
   int scelta, nuoviPosti;

   printf("inserisci il codice del volo che vuoi modificare: ");
   scanf("%s", codiceCercato);

   /* ciclo per trovare il codice ricercato con doppia condizione di terminazione 
     */
   while ( laLista && strcmp(laLista->unVolo.codice, codiceCercato ) )
      laLista = laLista->nextVolo;

   /* si verifica se la terminazione e` avvenuta 
      - perche' il codice e` stato trovato 
	  - o perche' la lista e` vuota
	*/
   if (laLista)
   {
      do {
         printf(  "MODIFICA VOLO\n"
                  "1 -- l'orario\n"
                  "2 -- il comandante\n"
                  "3 -- la prenotazione dei posti\n"
                  "4 -- visualizza volo\n"
                  "0 -- nient'altro! Grazie!\n");
         scanf("%d", &scelta);
         switch(scelta) {
            case 1:
               /*modifica Orario del volo */
               printf("il vecchio orario del volo era: %d:%d\n",
                       laLista->unVolo.ora, laLista->unVolo.minuti);
               printf("inserisci il nuovo orario: ");
               scanf( "%d %d", &laLista->unVolo.ora, &laLista->unVolo.minuti);
               break;
            case 2:
               /*modifica il nome del Comandante*/
               printf("il precedente comandante del volo era: %s\n",
                       laLista->unVolo.comandante);
               printf("inserisci il nuovo comandante: ");
               scanf( "%s", buffer);
               free(laLista->unVolo.comandante);
               laLista->unVolo.comandante = malloc(sizeof(strlen(buffer)+1) );
               strcpy(laLista->unVolo.comandante, buffer);
               break;
            case 3:
               /*modifica il numero dei posti*/
               printf("il precedente numero dei posti del volo era: %d\n",
                       laLista->unVolo.postiLiberi);
               printf("inserisci la quantita' di posti che vuoi riservare o liberare: ");
               scanf( "%d", &nuoviPosti);
               if (laLista->unVolo.postiLiberi - nuoviPosti < 0)
                  printf("hai richiesto troppi posti!\n");
               else
                  if ( (nuoviPosti < 0) &&
                     (laLista->unVolo.postiLiberi - nuoviPosti > laLista->unVolo.postiTotali) )
                     printf("non si possono liberare + di %d posti!",
                              laLista->unVolo.postiTotali - laLista->unVolo.postiLiberi);
                  else
                     laLista->unVolo.postiLiberi -= nuoviPosti;
               break;
            case 4:
               printf("il volo e` cosi` memorizzato: \n");
               stampaNodo(laLista);
               break;
            default:
               break;
         } /* fine switch */
      } while (scelta);
   }
   else
      printf("spiacente, il volo non esiste!\n");
}

void leggiVoli(tLista *pLista, char *nomeFile) {
   tVolo voloAppoggio;
   char bufferDestinazione[50], bufferComandante[40];
   FILE *idFile;
   int res;


   idFile = fopen(nomeFile,"r");
   if (idFile)
   {
      fseek(idFile,0, SEEK_SET);

      while ( !feof(idFile) )
      {
         res = fscanf(idFile, "%s %s %s %d %d %d %d",
               voloAppoggio.codice,
               bufferDestinazione,
               bufferComandante,
               &voloAppoggio.ora,
               &voloAppoggio.minuti,
               &voloAppoggio.postiLiberi,
               &voloAppoggio.postiTotali);

         /*verifica della lettura di almeno un elemento
           identificato dal formato di fscanf: correta lettura di un elemento*/
         if (res != -1)
         {
            voloAppoggio.destinazione = malloc(sizeof(strlen(bufferDestinazione)+1) );
            strcpy(voloAppoggio.destinazione, bufferDestinazione);

            voloAppoggio.comandante = malloc(sizeof(strlen(bufferComandante)+1) );
            strcpy(voloAppoggio.comandante, bufferComandante);

            insTestaLista( pLista, &voloAppoggio);
         }
      }

      fclose(idFile);
   }
   else
      printf("impossibile aprire il file\n");
}



void memorizzaVoli(tLista pNodo, char *nomefile) {
   FILE *idFile;
   tLista app;

   idFile = fopen(nomefile, "w");

   while (pNodo) {
      app = pNodo;
      fprintf(idFile, "%-6s\t%-12s\t%-15s\t%d %d\t%d\t%d\n",
              pNodo->unVolo.codice,
              pNodo->unVolo.destinazione,
              pNodo->unVolo.comandante,
              pNodo->unVolo.ora,
              pNodo->unVolo.minuti,
              pNodo->unVolo.postiTotali,
              pNodo->unVolo.postiLiberi);
      pNodo = pNodo->nextVolo;
      /*libera la memoria allocata dinamicamente*/
      free(app->unVolo.destinazione);
      free(app->unVolo.comandante);
      free(app);
   }

   fclose(idFile);
}


void cancellaVolo(tLista *pLista) {
   tNodo *pApp, *pScorrimento;
   char codiceCancellazione[6];

   printf("quale volo vuoi cancellare ? ");
   scanf("%s", codiceCancellazione);

   pScorrimento = *pLista; /*inizioLista*/

   /*confronta il codice da cancellare con quello presente nel nodo*/
   if ( strcmp(pScorrimento->unVolo.codice, codiceCancellazione) ) {
      pApp = pScorrimento;   /*inizializza i valori che permettono di effettuare correttamente
                               il ri-collegamento*/
      pScorrimento = pScorrimento->nextVolo;

      while (pScorrimento && strcmp(pScorrimento->unVolo.codice, codiceCancellazione) ) {
         pApp = pScorrimento;
         pScorrimento = pScorrimento->nextVolo; /*aggiorna i valori che permettono di effettuare
                                                  correttamente il ri-collegamento*/
      }
     
	  if (pScorrimento) {     /* se la lista non e` terminata */
         pApp->nextVolo = pScorrimento->nextVolo;

		 /* libera nella sequenza corretta la memoria allocata */
         free (pScorrimento->unVolo.destinazione);   
         free (pScorrimento->unVolo.comandante);
         free (pScorrimento);
      } else
         printf("volo non presente!\n");
   }
   else {           /* il volo da cancellare e` proprio il primo */
      *pLista = pScorrimento->nextVolo;
      free (pScorrimento->unVolo.destinazione);
      free (pScorrimento->unVolo.comandante);
      free (pScorrimento);
   }
}
